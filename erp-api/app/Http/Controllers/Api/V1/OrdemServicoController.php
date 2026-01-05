<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\OsCriadaEvent;
use App\Events\OsStatusMovidaEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\OrdemServico;
use App\Models\OsItem;
use App\Models\OsHistorico;
use App\Models\Produto;
use App\Models\ProdutoComposto;
use App\Models\Cliente;
use App\Services\OrdemServicoValorService;
use App\Services\EstoqueService;
use App\Services\ProdutoPrecoService;
use App\Services\ProdutoFatorBaseService;
use App\Services\ProdutoVivoCalculoService;
use App\Services\AuditoriaService;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppConfiguracao;
use App\Models\FinanceiroLancamento;
use App\Models\Pagamento;
use Illuminate\Http\Response;

class OrdemServicoController extends Controller
{
    private const STATUS_CANONICOS = [
        'aberta',
        'em_producao',
        'aguardando_pagamento',
        'finalizada',
        'cancelada',
    ];

    private const STATUS_PERMITIDOS = [
        // canônicos
        'aberta',
        'em_producao',
        'aguardando_pagamento',
        'finalizada',
        'cancelada',

        // compat legado
        'criada',
        'em_andamento',
        'producao',
        'faturado',
        'pendencia',
        'pendente',
    ];

    private function normalizeStatus(string $status): string
    {
        $s = trim($status);

        if ($s === 'criada') {
            return 'aberta';
        }

        if (in_array($s, ['em_andamento', 'producao'], true)) {
            return 'em_producao';
        }

        if (in_array($s, ['faturado', 'pendencia', 'pendente'], true)) {
            return 'aguardando_pagamento';
        }

        return $s;
    }

    private function isValidStatusDestino(string $status): bool
    {
        return in_array($status, self::STATUS_CANONICOS, true);
    }

    private function assertOrdemNaoFinalizadaOuCancelada(OrdemServico $ordem): ?JsonResponse
    {
        $statusCanonico = $this->normalizeStatus((string) $ordem->status_atual);
        if (in_array($statusCanonico, ['finalizada', 'cancelada'], true)) {
            return response()->json([
                'message' => 'Não é permitido alterar uma OS finalizada ou cancelada.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    private function assertOrdemTemItensAtivos(OrdemServico $ordem): ?JsonResponse
    {
        $itensAtivosCount = OsItem::where('empresa_id', $ordem->empresa_id)
            ->where('ordem_servico_id', $ordem->id)
            ->where('status', 'ativo')
            ->count();

        if ($itensAtivosCount <= 0) {
            return response()->json([
                'message' => 'A OS precisa ter ao menos 1 item para ser finalizada.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    private function isTransicaoPermitida(string $statusAtualCanonico, string $statusDestino): bool
    {
        if ($statusDestino === 'cancelada') {
            return ! in_array($statusAtualCanonico, ['cancelada', 'finalizada'], true);
        }

        if ($statusAtualCanonico === 'aberta' && $statusDestino === 'em_producao') {
            return true;
        }

        if ($statusAtualCanonico === 'em_producao' && $statusDestino === 'aguardando_pagamento') {
            return true;
        }

        if ($statusAtualCanonico === 'aguardando_pagamento' && $statusDestino === 'finalizada') {
            return true;
        }

        return false;
    }

    private function produtoPreco(Produto $produto): float
    {
        return app(ProdutoPrecoService::class)->precoAtual($produto);
    }

    private function resolveProdutoIdFromItem(int $empresaId, array $it): ?int
    {
        $produtoId = isset($it['produto_id']) ? (int) $it['produto_id'] : null;
        if ($produtoId) {
            return $produtoId;
        }

        $compostoId = isset($it['produto_composto_id']) ? (int) $it['produto_composto_id'] : null;
        if (! $compostoId) {
            return null;
        }

        // Compat: produtos_compostos foram migrados para produtos, mantendo legacy_produto_composto_id
        $pid = Produto::query()
            ->where('empresa_id', $empresaId)
            ->where('legacy_produto_composto_id', $compostoId)
            ->value('id');

        return $pid ? (int) $pid : null;
    }

    /**
     * Expande itens que podem conter produto simples (produto_id) ou composto (produto_composto_id).
     * Sempre retorna uma lista de itens com produto_id + quantidade (itens simples).
     *
     * @param int $empresaId
     * @param array<int,array<string,mixed>> $itens
     * @return array<int,array{produto_id:int, quantidade:float, origem?:string, origem_id?:int}>
     */
    private function expandirItens(int $empresaId, array $itens): array
    {
        $expanded = [];

        foreach ($itens as $it) {
            $q = isset($it['quantidade']) ? (float) $it['quantidade'] : 0.0;
            if ($q <= 0) {
                continue;
            }

            $produtoId = isset($it['produto_id']) ? (int) $it['produto_id'] : null;
            $compostoId = isset($it['produto_composto_id']) ? (int) $it['produto_composto_id'] : null;

            if ($produtoId) {
                $produto = Produto::with(['materiaisPivot'])
                    ->where('empresa_id', $empresaId)
                    ->where('id', $produtoId)
                    ->where(function ($q) {
                        $q->where('ativo', true)->orWhere('status', 'ativo');
                    })
                    ->first();

                if (! $produto) {
                    throw new \RuntimeException('Produto não encontrado.');
                }

                $materiais = $produto->materiaisPivot;

                if ($materiais && $materiais->count() > 0) {
                    foreach ($materiais as $m) {
                        $expanded[] = [
                            'produto_id' => (int) ($m->material_id ?? $m->material_produto_id),
                            'quantidade' => $q * (float) $m->quantidade,
                            'origem' => 'produto',
                            'origem_id' => (int) $produto->id,
                        ];
                    }
                } else {
                    $expanded[] = [
                        'produto_id' => $produtoId,
                        'quantidade' => $q,
                    ];
                }
                continue;
            }

            if ($compostoId) {
                $composto = ProdutoComposto::with(['itens'])
                    ->where('empresa_id', $empresaId)
                    ->where('id', $compostoId)
                    ->where('status', 'ativo')
                    ->first();

                if (! $composto) {
                    throw new \RuntimeException('Produto composto não encontrado.');
                }

                foreach ($composto->itens as $ci) {
                    $expanded[] = [
                        'produto_id' => (int) $ci->produto_id,
                        'quantidade' => $q * (float) $ci->quantidade,
                        'origem' => 'composto',
                        'origem_id' => (int) $composto->id,
                    ];
                }
            }
        }

        return $expanded;
    }

    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $ordens = OrdemServico::with(['cliente', 'itens.produto'])
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (OrdemServico $os) {
                $itensAtivos = $os->itens->where('status', 'ativo')->values();
                $produtos = $itensAtivos
                    ->map(fn (OsItem $it) => $it->produto ? (string) $it->produto->nome : null)
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $os->id,
                    'numero_os' => $os->numero,
                    'cliente' => $os->cliente,
                    'status' => $this->normalizeStatus((string) $os->status_atual),
                    'valor_total' => (float) $os->valor_total,
                    'created_at' => $os->created_at,
                    'itens_count' => (int) $itensAtivos->count(),
                    'produtos' => $produtos,
                ];
            });

        return response()->json(['data' => $ordens], Response::HTTP_OK);
    }

    public function store(Request $request, AuditoriaService $auditoria): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'cliente_id' => 'required|integer',
            'observacoes' => 'nullable|string',
            'descricao' => 'nullable|string',
            'data_entrega' => 'sometimes|date',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'nullable|integer',
            'itens.*.produto_composto_id' => 'nullable|integer',
            'itens.*.quantidade' => 'sometimes|numeric|min:0.0001',
            'itens.*.comprimento' => 'sometimes|numeric|min:0.0001',
            'itens.*.largura' => 'sometimes|numeric|min:0.0001',
            'itens.*.altura' => 'sometimes|numeric|min:0.0001',
        ]);

        foreach ($validated['itens'] as $it) {
            $hasProduto = array_key_exists('produto_id', $it) && ! empty($it['produto_id']);
            $hasComposto = array_key_exists('produto_composto_id', $it) && ! empty($it['produto_composto_id']);
            if ($hasProduto === $hasComposto) {
                return response()->json(['message' => 'Informe produto_id OU produto_composto_id em cada item.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->where('id', $validated['cliente_id'])
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Generate order number per empresa: OS-000001 style
        $last = OrdemServico::where('empresa_id', $empresaId)
            ->orderBy('id', 'desc')
            ->first();

        $next = 1;
        if ($last && preg_match('/\d+$/', $last->numero, $m)) {
            $next = (int)$m[0] + 1;
        } elseif ($last && preg_match('/OS-(\d+)/', $last->numero, $m2)) {
            $next = (int)$m2[1] + 1;
        } elseif ($last) {
            $next = $last->id + 1;
        }
        $numero = 'OS-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

        $ordem = OrdemServico::create([
            'empresa_id' => $empresaId,
            'cliente_id' => $cliente->id,
            'numero' => $numero,
            'descricao' => $validated['observacoes'] ?? ($validated['descricao'] ?? null),
            'data_entrega' => $validated['data_entrega'] ?? now()->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 0,
        ]);

        // Registrar histórico inicial
        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : 0,
            'status_anterior' => '',
            'status_novo' => 'aberta',
        ]);

        foreach ($validated['itens'] as $it) {
            $pid = $this->resolveProdutoIdFromItem((int) $empresaId, $it);
            if (! $pid) {
                return response()->json(['message' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
            }

            $produto = Produto::where('empresa_id', $empresaId)
                ->where('id', $pid)
                ->where(function ($q) {
                    $q->where('ativo', true)->orWhere('status', 'ativo');
                })
                ->first();

            if (! $produto) {
                return response()->json(['message' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
            }

            try {
                $fatorInfo = app(ProdutoFatorBaseService::class)->fromInput($produto, $it);
                $calc = app(ProdutoVivoCalculoService::class)->calcularParaFator($produto, (float) $fatorInfo['fator']);
            } catch (\Throwable $e) {
                $status = (int) $e->getCode();
                if ($status < 400 || $status >= 600) {
                    $status = Response::HTTP_UNPROCESSABLE_ENTITY;
                }
                return response()->json(['message' => $e->getMessage()], $status);
            }

            OsItem::create([
                'empresa_id' => $empresaId,
                'ordem_servico_id' => $ordem->id,
                'produto_id' => $produto->id,
                // quantidade fica como fator normalizado (un/metro/m²)
                'quantidade' => (float) $fatorInfo['quantidade'],
                'comprimento' => $fatorInfo['comprimento'],
                'largura' => $fatorInfo['largura'],
                'altura' => $fatorInfo['altura'],
                'valor_unitario' => (float) $calc['valor_unitario'],
                'valor_total' => (float) $calc['preco_total'],
                'status' => 'ativo',
            ]);
        }

        $service = new OrdemServicoValorService();
        $service->recalcularValor($ordem);

        $ordem->load(['cliente', 'itens.produto']);

        $payload = [
            'id' => $ordem->id,
            'numero_os' => $ordem->numero,
            'cliente' => $ordem->cliente,
            'status' => $this->normalizeStatus((string) $ordem->status_atual),
            'observacoes' => $ordem->descricao,
            'valor_total' => (float) $ordem->valor_total,
            'created_at' => $ordem->created_at,
            'itens' => $ordem->itens->where('status', 'ativo')->values(),
        ];

        $auditoria->log($request, 'create', 'os', (int) $ordem->id, null, [
            'cliente_id' => (int) $ordem->cliente_id,
            'numero' => (string) $ordem->numero,
            'status' => (string) $ordem->status_atual,
            'valor_total' => (float) $ordem->valor_total,
        ]);

        // Automações por evento (best-effort)
        try {
            event(new OsCriadaEvent((int) $empresaId, (int) $ordem->id, $user ? (int) $user->id : 0));
        } catch (\Throwable $e) {
            // não bloquear
        }

        return response()->json(['data' => $payload], Response::HTTP_CREATED);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $ordem = OrdemServico::with(['cliente', 'itens.produto'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $payload = [
            'id' => $ordem->id,
            'numero_os' => $ordem->numero,
            'cliente' => $ordem->cliente,
            'status' => $this->normalizeStatus((string) $ordem->status_atual),
            'observacoes' => $ordem->descricao,
            'valor_total' => (float) $ordem->valor_total,
            'created_at' => $ordem->created_at,
            'itens' => $ordem->itens->where('status', 'ativo')->values(),
        ];

        return response()->json(['data' => $payload], Response::HTTP_OK);
    }

    public function update(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'status' => 'sometimes|string',
            'observacoes' => 'sometimes|nullable|string',
            'itens' => 'sometimes|array|min:1',
            'itens.*.produto_id' => 'nullable|integer',
            'itens.*.produto_composto_id' => 'nullable|integer',
            'itens.*.quantidade' => 'sometimes|numeric|min:0.0001',
            'itens.*.comprimento' => 'sometimes|numeric|min:0.0001',
            'itens.*.largura' => 'sometimes|numeric|min:0.0001',
            'itens.*.altura' => 'sometimes|numeric|min:0.0001',
        ]);

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], Response::HTTP_NOT_FOUND);
        }

        if ($resp = $this->assertOrdemNaoFinalizadaOuCancelada($ordem)) {
            return $resp;
        }

        $antes = [
            'status_atual' => (string) $ordem->status_atual,
            'descricao' => (string) ($ordem->descricao ?? ''),
            'valor_total' => (float) $ordem->valor_total,
        ];

        if (array_key_exists('status', $validated)) {
            return response()->json([
                'message' => 'Alteração de status deve usar o endpoint de status da OS.',
                'errors' => [
                    'status' => ['Use PUT /api/v1/ordens-servico/{id}/status com status_destino.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('observacoes', $validated)) {
            $ordem->descricao = $validated['observacoes'];
        }

        $ordem->save();

        if (array_key_exists('itens', $validated)) {
            foreach ($validated['itens'] as $it) {
                $hasProduto = array_key_exists('produto_id', $it) && ! empty($it['produto_id']);
                $hasComposto = array_key_exists('produto_composto_id', $it) && ! empty($it['produto_composto_id']);
                if ($hasProduto === $hasComposto) {
                    return response()->json(['message' => 'Informe produto_id OU produto_composto_id em cada item.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            // desativa itens antigos
            OsItem::where('empresa_id', $empresaId)
                ->where('ordem_servico_id', $ordem->id)
                ->where('status', 'ativo')
                ->update(['status' => 'inativo']);

            foreach ($validated['itens'] as $it) {
                $pid = $this->resolveProdutoIdFromItem((int) $empresaId, $it);
                if (! $pid) {
                    return response()->json(['message' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
                }

                $produto = Produto::where('empresa_id', $empresaId)
                    ->where('id', $pid)
                    ->where(function ($q) {
                        $q->where('ativo', true)->orWhere('status', 'ativo');
                    })
                    ->first();

                if (! $produto) {
                    return response()->json(['message' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
                }

                try {
                    $fatorInfo = app(ProdutoFatorBaseService::class)->fromInput($produto, $it);
                    $calc = app(ProdutoVivoCalculoService::class)->calcularParaFator($produto, (float) $fatorInfo['fator']);
                } catch (\Throwable $e) {
                    $status = (int) $e->getCode();
                    if ($status < 400 || $status >= 600) {
                        $status = Response::HTTP_UNPROCESSABLE_ENTITY;
                    }
                    return response()->json(['message' => $e->getMessage()], $status);
                }

                OsItem::create([
                    'empresa_id' => $empresaId,
                    'ordem_servico_id' => $ordem->id,
                    'produto_id' => $produto->id,
                    'quantidade' => (float) $fatorInfo['quantidade'],
                    'comprimento' => $fatorInfo['comprimento'],
                    'largura' => $fatorInfo['largura'],
                    'altura' => $fatorInfo['altura'],
                    'valor_unitario' => (float) $calc['valor_unitario'],
                    'valor_total' => (float) $calc['preco_total'],
                    'status' => 'ativo',
                ]);
            }

            $service = new OrdemServicoValorService();
            $service->recalcularValor($ordem);
        }

        $ordem->load(['cliente', 'itens.produto']);

        $payload = [
            'id' => $ordem->id,
            'numero_os' => $ordem->numero,
            'cliente' => $ordem->cliente,
            'status' => $this->normalizeStatus((string) $ordem->status_atual),
            'observacoes' => $ordem->descricao,
            'valor_total' => (float) $ordem->valor_total,
            'created_at' => $ordem->created_at,
            'itens' => $ordem->itens->where('status', 'ativo')->values(),
        ];

        $depois = [
            'status_atual' => (string) $ordem->status_atual,
            'descricao' => (string) ($ordem->descricao ?? ''),
            'valor_total' => (float) $ordem->valor_total,
        ];

        $auditoria->log($request, 'update', 'os', (int) $ordem->id, $antes, $depois);

        return response()->json(['data' => $payload], Response::HTTP_OK);
    }

    public function adicionarItem(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'produto_id' => 'nullable|integer',
            'produto_composto_id' => 'nullable|integer',
            'quantidade' => 'sometimes|numeric|min:0.0001',
            'comprimento' => 'sometimes|numeric|min:0.0001',
            'largura' => 'sometimes|numeric|min:0.0001',
            'altura' => 'sometimes|numeric|min:0.0001',
        ]);

        $hasProduto = array_key_exists('produto_id', $validated) && ! empty($validated['produto_id']);
        $hasComposto = array_key_exists('produto_composto_id', $validated) && ! empty($validated['produto_composto_id']);
        if ($hasProduto === $hasComposto) {
            return response()->json(['message' => 'Informe produto_id OU produto_composto_id.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        if ($resp = $this->assertOrdemNaoFinalizadaOuCancelada($ordem)) {
            return $resp;
        }

        $pid = $hasProduto
            ? (int) $validated['produto_id']
            : (int) ($this->resolveProdutoIdFromItem((int) $empresaId, $validated) ?? 0);

        if (! $pid) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $pid)
            ->where(function ($q) {
                $q->where('ativo', true)->orWhere('status', 'ativo');
            })
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        try {
            $fatorInfo = app(ProdutoFatorBaseService::class)->fromInput($produto, $validated);
            $calc = app(ProdutoVivoCalculoService::class)->calcularParaFator($produto, (float) $fatorInfo['fator']);
        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status >= 600) {
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
            return response()->json(['message' => $e->getMessage()], $status);
        }

        $created = OsItem::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'produto_id' => $produto->id,
            'quantidade' => (float) $fatorInfo['quantidade'],
            'comprimento' => $fatorInfo['comprimento'],
            'largura' => $fatorInfo['largura'],
            'altura' => $fatorInfo['altura'],
            'valor_unitario' => (float) $calc['valor_unitario'],
            'valor_total' => (float) $calc['preco_total'],
            'status' => 'ativo',
        ]);

        // Recalcular valor da OS
        $service = new OrdemServicoValorService();
        $service->recalcularValor($ordem);

        $created->load('produto');
        return response()->json($created, 201);
    }

    public function removerItem(Request $request, $id, $itemId): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        if ($resp = $this->assertOrdemNaoFinalizadaOuCancelada($ordem)) {
            return $resp;
        }

        $item = OsItem::where('empresa_id', $empresaId)
            ->where('ordem_servico_id', $ordem->id)
            ->where('id', $itemId)
            ->where('status', 'ativo')
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Item não encontrado'], 404);
        }

        $item->status = 'inativo';
        $item->save();

        // Recalcular valor da OS
        $service = new OrdemServicoValorService();
        $service->recalcularValor($ordem);

        return response()->json(['message' => 'Item marcado como inativo']);
    }

    public function atualizarStatus(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        $statusAnterior = $ordem->status_atual;
        $statusNovoRaw = (string) $validated['status'];

        // Validação do input (aceita legado), mas sempre processa/transiciona em canônico.
        if (! in_array($statusNovoRaw, self::STATUS_PERMITIDOS, true)) {
            return response()->json(['message' => 'Status inválido.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $statusAtualCanonico = $this->normalizeStatus((string) $statusAnterior);
        $statusNovoCanonico = $this->normalizeStatus($statusNovoRaw);

        if (! $this->isValidStatusDestino($statusNovoCanonico)) {
            return response()->json(['message' => 'Status inválido.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($statusAtualCanonico !== $statusNovoCanonico && ! $this->isTransicaoPermitida($statusAtualCanonico, $statusNovoCanonico)) {
            return response()->json([
                'message' => 'Transição de status inválida.',
                'errors' => [
                    'status' => ["Transição inválida: {$statusAtualCanonico} → {$statusNovoCanonico}."],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($statusNovoCanonico === 'finalizada' && $statusAnterior !== 'finalizada') {
            if ($resp = $this->assertOrdemTemItensAtivos($ordem)) {
                return $resp;
            }
        }

        // Se está finalizando agora, tenta consumir estoque antes de gravar.
        if ($statusNovoCanonico === 'finalizada' && $statusAnterior !== 'finalizada') {
            try {
                app(EstoqueService::class)->consumirPorOrdemServico($ordem);
            } catch (\Throwable $e) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $ordem->status_atual = $statusNovoCanonico;
        $ordem->save();

        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : 0,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovoCanonico,
        ]);

        $auditoria->log($request, 'status_change', 'os', (int) $ordem->id, [
            'status_atual' => (string) $statusAnterior,
        ], [
            'status_atual' => (string) $statusNovoCanonico,
        ]);

        // Automações por evento (best-effort)
        try {
            event(new OsStatusMovidaEvent((int) $empresaId, (int) $ordem->id, (string) $statusAnterior, (string) $statusNovoCanonico, $user ? (int) $user->id : 0));
        } catch (\Throwable $e) {
            // não bloquear
        }

        return response()->json($ordem);
    }

    public function atualizarStatusDestino(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        $validated = $request->validate([
            'status_destino' => 'required|string',
        ]);

        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $statusAnterior = (string) $ordem->status_atual;
        $statusAtualCanonico = $this->normalizeStatus($statusAnterior);
        $statusDestino = trim((string) $validated['status_destino']);

        if (! $this->isValidStatusDestino($statusDestino)) {
            return response()->json([
                'message' => 'Status destino inválido.',
                'errors' => [
                    'status_destino' => ['Status destino inválido.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($statusAtualCanonico === $statusDestino) {
            return response()->json($ordem);
        }

        if (! $this->isTransicaoPermitida($statusAtualCanonico, $statusDestino)) {
            return response()->json([
                'message' => 'Transição de status inválida.',
                'errors' => [
                    'status_destino' => ["Transição inválida: {$statusAtualCanonico} → {$statusDestino}."],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($statusDestino === 'finalizada' && $statusAnterior !== 'finalizada') {
            if ($resp = $this->assertOrdemTemItensAtivos($ordem)) {
                return $resp;
            }
        }

        if ($statusDestino === 'finalizada' && $statusAnterior !== 'finalizada') {
            try {
                app(EstoqueService::class)->consumirPorOrdemServico($ordem);
            } catch (\Throwable $e) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $ordem->status_atual = $statusDestino;
        $ordem->save();

        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : 0,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusDestino,
        ]);

        $auditoria->log($request, 'status_change', 'os', (int) $ordem->id, [
            'status_atual' => (string) $statusAnterior,
        ], [
            'status_atual' => (string) $statusDestino,
        ]);

        // Automações por evento (best-effort)
        try {
            event(new OsStatusMovidaEvent((int) $empresaId, (int) $ordem->id, (string) $statusAnterior, (string) $statusDestino, $user ? (int) $user->id : 0));
        } catch (\Throwable $e) {
            // não bloquear
        }

        return response()->json($ordem);
    }

    public function destroy(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        $statusCanonico = $this->normalizeStatus((string) $ordem->status_atual);
        if (in_array($statusCanonico, ['finalizada', 'cancelada'], true)) {
            return response()->json(['message' => 'A OS já está finalizada ou cancelada.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $statusAnterior = $ordem->status_atual;
        $ordem->status_atual = 'cancelada';
        $ordem->save();

        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : 0,
            'status_anterior' => $statusAnterior,
            'status_novo' => 'cancelada',
        ]);

        $auditoria->log($request, 'cancel', 'os', (int) $ordem->id, [
            'status_atual' => (string) $statusAnterior,
        ], [
            'status_atual' => 'cancelada',
        ]);

        return response()->json(['message' => 'Ordem de serviço marcada como cancelada']);
    }
}
