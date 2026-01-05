<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\CompraCabecalho;
use App\Models\CompraItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompraController extends Controller
{
    private function normalizeNomeItem(string $nome): string
    {
        $nome = trim($nome);
        $nome = preg_replace('/\s+/', ' ', $nome) ?? $nome;
        return $nome;
    }

    private function normalizeUnidadeCompra(?string $unidade): ?string
    {
        if ($unidade === null) {
            return null;
        }
        $u = trim($unidade);
        $u = preg_replace('/\s+/', ' ', $u) ?? $u;
        return $u !== '' ? $u : null;
    }

    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $query = CompraCabecalho::query()
            ->where('empresa_id', $empresaId)
            ->with(['itens.item'])
            ->orderByDesc('data')
            ->orderByDesc('id');

        $rows = $query->get();

        return response()->json(['data' => $rows], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        // Payload definitivo: cabeçalho + itens
        // Mantemos compatibilidade temporária com o payload antigo (item único) para não quebrar integrações existentes.
        $validated = $request->validate([
            'data' => 'required|date',
            'fornecedor' => 'sometimes|nullable|string',
            'observacoes' => 'sometimes|nullable|string',

            'itens' => 'sometimes|array|min:1',
            'itens.*.nome' => 'required_with:itens|string',
            'itens.*.tipo' => 'required_with:itens|string|in:material,insumo,equipamento',
            'itens.*.unidade_compra' => 'required_with:itens|string|max:20',
            'itens.*.quantidade' => 'required_with:itens|numeric|min:0.0001',
            'itens.*.valor_total' => 'required_with:itens|numeric|min:0.01',

            // Legado (item único)
            'compra_item_id' => 'sometimes|nullable|integer',
            'tipo' => 'sometimes|string|in:material,insumo,equipamento',
            'nome' => 'sometimes|string',
            'unidade_compra' => 'sometimes|string|max:20',
            'quantidade' => 'sometimes|numeric|min:0.0001',
            'valor_total' => 'sometimes|numeric|min:0.01',
        ]);

        $normalizeAndResolveItem = function (array $linha) use ($empresaId) {
            $item = null;

            $itemId = (int) ($linha['compra_item_id'] ?? 0);
            if ($itemId > 0) {
                $item = CompraItem::query()
                    ->where('empresa_id', $empresaId)
                    ->where('id', $itemId)
                    ->first();
            }

            if (! $item) {
                $nome = $this->normalizeNomeItem((string) ($linha['nome'] ?? ''));
                $tipo = (string) ($linha['tipo'] ?? '');
                $unidadeCompra = $this->normalizeUnidadeCompra($linha['unidade_compra'] ?? null);

                $item = CompraItem::query()
                    ->where('empresa_id', $empresaId)
                    ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome, 'UTF-8')])
                    ->first();

                if (! $item) {
                    $item = new CompraItem();
                    $item->empresa_id = (int) $empresaId;
                    $item->tipo = $tipo;
                    $item->nome = $nome;
                    $item->unidade_compra = $unidadeCompra;
                    $item->ativo = true;
                    $item->save();
                } else {
                    if ($tipo !== '' && (string) $item->tipo !== '' && (string) $item->tipo !== $tipo) {
                        throw new \RuntimeException('Item já existe com outro tipo. Use o tipo correto para este nome.', 422);
                    }

                    $oldUn = $this->normalizeUnidadeCompra($item->unidade_compra);
                    if ($unidadeCompra !== null && $oldUn !== null && mb_strtolower($oldUn, 'UTF-8') !== mb_strtolower($unidadeCompra, 'UTF-8')) {
                        throw new \RuntimeException('Unidade de compra difere do cadastro do item. Ajuste a unidade para a unidade original do item.', 422);
                    }

                    if ($item->unidade_compra === null && $unidadeCompra !== null) {
                        $item->unidade_compra = $unidadeCompra;
                    }
                    if (! $item->tipo && $tipo) {
                        $item->tipo = $tipo;
                    }
                    if ($item->ativo === false) {
                        $item->ativo = true;
                    }
                    if ($item->isDirty()) {
                        $item->save();
                    }
                }
            }

            if (! $item) {
                throw new \RuntimeException('Item de compra não encontrado');
            }

            return $item;
        };

        $updateMedia = function (CompraItem $item, float $quantidade, float $custoUnitario) {
            $oldQtyBase = (float) ($item->preco_medio_qtd_base ?? 0);
            $oldPrecoMedio = (float) ($item->preco_medio ?? 0);

            $newQtyBase = $oldQtyBase + $quantidade;
            $newPrecoMedio = $newQtyBase > 0
                ? round((($oldPrecoMedio * $oldQtyBase) + ($custoUnitario * $quantidade)) / $newQtyBase, 4)
                : 0.0;

            $item->preco_medio_qtd_base = $newQtyBase;
            $item->preco_medio = $newPrecoMedio;
            $item->preco_ultimo = $custoUnitario;
            $item->save();
        };

        try {
            $result = DB::transaction(function () use ($empresaId, $validated, $normalizeAndResolveItem, $updateMedia) {
                $fornecedor = array_key_exists('fornecedor', $validated)
                    ? ($validated['fornecedor'] !== null ? trim((string) $validated['fornecedor']) : null)
                    : null;
                $observacoes = array_key_exists('observacoes', $validated)
                    ? ($validated['observacoes'] !== null ? trim((string) $validated['observacoes']) : null)
                    : null;

                $cabecalho = new CompraCabecalho();
                $cabecalho->empresa_id = (int) $empresaId;
                $cabecalho->data = (string) $validated['data'];
                $cabecalho->fornecedor = $fornecedor;
                $cabecalho->observacoes = $observacoes;
                $cabecalho->save();

                $linhas = [];

                $itensPayload = $validated['itens'] ?? null;
                if (is_array($itensPayload) && count($itensPayload) > 0) {
                    foreach ($itensPayload as $linha) {
                        $item = $normalizeAndResolveItem($linha);

                        $quantidade = (float) $linha['quantidade'];
                        $valorTotal = (float) $linha['valor_total'];
                        $custoUnitario = $quantidade > 0 ? round($valorTotal / $quantidade, 4) : 0.0;

                        $compra = new Compra();
                        $compra->empresa_id = (int) $empresaId;
                        $compra->compra_id = (int) $cabecalho->id;
                        $compra->compra_item_id = (int) $item->id;
                        $compra->data = (string) $validated['data'];
                        $compra->fornecedor = $fornecedor;
                        $compra->quantidade = $quantidade;
                        $compra->valor_total = $valorTotal;
                        $compra->custo_unitario = $custoUnitario;
                        $compra->observacoes = $observacoes;
                        $compra->save();

                        $updateMedia($item, $quantidade, $custoUnitario);

                        $linhas[] = $compra;
                    }
                } else {
                    // Legado (item único)
                    $linha = [
                        'compra_item_id' => $validated['compra_item_id'] ?? null,
                        'tipo' => $validated['tipo'] ?? null,
                        'nome' => $validated['nome'] ?? null,
                        'unidade_compra' => $validated['unidade_compra'] ?? null,
                        'quantidade' => $validated['quantidade'] ?? null,
                        'valor_total' => $validated['valor_total'] ?? null,
                    ];

                    if (! $linha['quantidade'] || ! $linha['valor_total']) {
                        throw new \RuntimeException('Informe itens da compra.', 422);
                    }

                    $item = $normalizeAndResolveItem($linha);

                    $quantidade = (float) $linha['quantidade'];
                    $valorTotal = (float) $linha['valor_total'];
                    $custoUnitario = $quantidade > 0 ? round($valorTotal / $quantidade, 4) : 0.0;

                    $compra = new Compra();
                    $compra->empresa_id = (int) $empresaId;
                    $compra->compra_id = (int) $cabecalho->id;
                    $compra->compra_item_id = (int) $item->id;
                    $compra->data = (string) $validated['data'];
                    $compra->fornecedor = $fornecedor;
                    $compra->quantidade = $quantidade;
                    $compra->valor_total = $valorTotal;
                    $compra->custo_unitario = $custoUnitario;
                    $compra->observacoes = $observacoes;
                    $compra->save();

                    $updateMedia($item, $quantidade, $custoUnitario);
                    $linhas[] = $compra;
                }

                $cabecalho->load(['itens.item']);
                return $cabecalho;
            });
        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status >= 600) {
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Compra registrada com sucesso',
            'data' => $result,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $row = CompraCabecalho::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->with(['itens.item'])
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Registro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $row], Response::HTTP_OK);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $row = CompraCabecalho::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Registro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($empresaId, $row) {
            Compra::query()
                ->where('empresa_id', $empresaId)
                ->where('compra_id', (int) $row->id)
                ->delete();
            $row->delete();
        });

        return response()->json(['message' => 'Compra removida com sucesso'], Response::HTTP_OK);
    }
}
