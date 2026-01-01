<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\OrdemServico;
use App\Models\OsItem;
use App\Models\OsHistorico;
use App\Models\Produto;
use App\Models\Cliente;
use App\Services\OrdemServicoValorService;

class OrdemServicoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $ordens = OrdemServico::where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (OrdemServico $os) {
                $cliente = Cliente::find($os->cliente_id);
                return [
                    'id' => $os->id,
                    'numero' => $os->numero,
                    'descricao' => $os->descricao,
                    'valor_total' => (float) $os->valor_total,
                    'data_entrega' => $os->data_entrega,
                    'status_atual' => $os->status_atual,
                    'created_at' => $os->created_at,
                    'cliente' => $cliente,
                ];
            });

        return response()->json($ordens);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'data_entrega' => 'required|date',
            'descricao' => 'nullable|string',
        ]);

        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

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
            'cliente_id' => $validated['cliente_id'],
            'numero' => $numero,
            'descricao' => $validated['descricao'] ?? null,
            'data_entrega' => $validated['data_entrega'],
            'status_atual' => 'criada',
            'valor_total' => 0,
        ]);

        // Registrar histórico inicial
        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : null,
            'status_anterior' => '',
            'status_novo' => 'criada',
        ]);

        return response()->json($ordem, 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $ordem = OrdemServico::with(['itens.produto', 'historicos'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        return response()->json($ordem);
    }

    public function adicionarItem(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'produto_id' => 'required|exists:produtos,id',
            'quantidade' => 'required|numeric',
            'largura' => 'nullable|numeric',
            'altura' => 'nullable|numeric',
        ]);

        $empresaId = $request->attributes->get('empresa_id');

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        $produto = Produto::where('id', $validated['produto_id'])->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        if ($produto->empresa_id !== $empresaId) {
            return response()->json(['message' => 'Produto não pertence à empresa'], 404);
        }

        $valorUnitario = (float) $produto->preco_final;
        $quantidade = (float) $validated['quantidade'];
        $valorTotal = $quantidade * $valorUnitario;

        $item = OsItem::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
            'largura' => $validated['largura'] ?? null,
            'altura' => $validated['altura'] ?? null,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $valorTotal,
            'status' => 'ativo',
        ]);

        // Recalcular valor da OS
        $service = new OrdemServicoValorService();
        $service->recalcularValor($ordem);

        $item->load('produto');

        return response()->json($item, 201);
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

    public function atualizarStatus(Request $request, $id): JsonResponse
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
        $statusNovo = $validated['status'];

        $ordem->status_atual = $statusNovo;
        $ordem->save();

        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : null,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
        ]);

        return response()->json($ordem);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        $statusAnterior = $ordem->status_atual;
        $ordem->status_atual = 'cancelada';
        $ordem->save();

        OsHistorico::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'usuario_id' => $user ? $user->id : null,
            'status_anterior' => $statusAnterior,
            'status_novo' => 'cancelada',
        ]);

        return response()->json(['message' => 'Ordem de serviço marcada como cancelada']);
    }
}
