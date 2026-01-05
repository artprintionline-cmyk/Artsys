<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Produto;
use App\Models\Componente;
use App\Models\ProdutoComponente;
use App\Services\ProdutoCustoService;
use App\Services\ProdutoVivoCalculoService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProdutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $produtos = Produto::where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get();

        return response()->json(['data' => $produtos], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'nome' => 'required|string',
            'descricao' => 'nullable|string',

            // Produto Vivo
            'custo_base' => 'sometimes|numeric|min:0',
            'forma_calculo' => 'required|string|in:unitario,metro_linear,metro_quadrado',
            'preco_base' => 'required|numeric|min:0',
            'controla_estoque' => 'sometimes|boolean',
            'ativo' => 'sometimes|boolean',

            // Produto é sempre vendável (não aceitar do cliente)
            'vendavel' => 'sometimes|boolean',

            // Compatibilidade (entrada antiga)
            'preco' => 'sometimes|numeric',
            'preco_venda' => 'sometimes|numeric',
            'vendavel' => 'sometimes|boolean',

            // Compras (custo real)
            'compras_itens' => 'sometimes|array',
            'compras_itens.*.compra_item_id' => 'required_with:compras_itens|integer',
            'compras_itens.*.quantidade_base' => 'required_with:compras_itens|numeric|min:0.0001',

            // Campos legados (opcionais)
            'tipo_medida' => 'sometimes|string',
            'largura_padrao' => 'sometimes|nullable|numeric',
            'altura_padrao' => 'sometimes|nullable|numeric',
        ]);

        try {
            /** @var Produto $produto */
            $produto = DB::transaction(function () use ($empresaId, $validated) {
                $calc = app(ProdutoVivoCalculoService::class);

                $produto = new Produto();
                $produto->empresa_id = $empresaId;
                $produto->nome = $validated['nome'];
                // SKU é automático (gerado no Model). Ignorar entrada do cliente.
                $produto->sku = null;
                $produto->descricao = $validated['descricao'] ?? null;

                $produto->custo_base = (float) ($validated['custo_base'] ?? 0);
                $produto->forma_calculo = (string) $validated['forma_calculo'];
                $produto->preco_base = (float) $validated['preco_base'];
                // compat
                $produto->preco_venda = (float) $produto->preco_base;
                $produto->controla_estoque = (bool) ($validated['controla_estoque'] ?? true);
                $produto->ativo = (bool) ($validated['ativo'] ?? true);
                $produto->vendavel = true;

                // compat legado
                $produto->preco = array_key_exists('preco', $validated)
                    ? (float) $validated['preco']
                    : (float) $produto->preco_base;
                $produto->descricao = $validated['descricao'] ?? null;

                // default para compatibilidade com schema existente
                $produto->tipo_medida = $validated['tipo_medida'] ?? (string) $produto->forma_calculo;
                $produto->largura_padrao = $validated['largura_padrao'] ?? null;
                $produto->altura_padrao = $validated['altura_padrao'] ?? null;

                // manter status legado sincronizado
                $produto->status = $produto->ativo ? 'ativo' : 'inativo';

                // iniciar calculados (serão recalculados após materiais)
                $produto->custo_total = 0;
                $produto->lucro = 0;
                $produto->margem_percentual = 0;

                $produto->save();

                if (array_key_exists('compras_itens', $validated)) {
                    $rowsIn = [];
                    foreach (($validated['compras_itens'] ?? []) as $r) {
                        $rowsIn[] = [
                            'compra_item_id' => (int) ($r['compra_item_id'] ?? 0),
                            'quantidade_base' => (float) ($r['quantidade_base'] ?? 0),
                        ];
                    }

                    $calc->validarComprasItens((int) $empresaId, $rowsIn);

                    foreach ($rowsIn as $r) {
                        \App\Models\ProdutoCompraItem::updateOrCreate(
                            [
                                'empresa_id' => (int) $empresaId,
                                'produto_id' => (int) $produto->id,
                                'compra_item_id' => (int) $r['compra_item_id'],
                            ],
                            [
                                'quantidade_base' => (float) $r['quantidade_base'],
                            ]
                        );
                    }
                }

                // Recalcular sempre (com ou sem compras_itens)
                $calc->recalcular($produto);

                $produto->load([
                    'comprasItensPivot.compraItem',
                ]);
                return $produto;
            });
        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status >= 600) {
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Registro salvo com sucesso',
            'data' => $produto,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $produto = Produto::with([
            'produtoComponentes.componente',
            'comprasItensPivot.compraItem',
        ])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $produto], Response::HTTP_OK);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'sometimes|string',
            'descricao' => 'sometimes|nullable|string',

            // Produto Vivo
            'custo_base' => 'sometimes|numeric',
            'forma_calculo' => 'sometimes|string|in:unitario,metro_linear,metro_quadrado',
            'preco_base' => 'sometimes|numeric',
            'preco_venda' => 'sometimes|numeric',
            'controla_estoque' => 'sometimes|boolean',
            'ativo' => 'sometimes|boolean',

            // Compat
            'preco' => 'sometimes|numeric',
            'vendavel' => 'sometimes|boolean',

            'tipo_medida' => 'sometimes|string',
            'largura_padrao' => 'sometimes|nullable|numeric',
            'altura_padrao' => 'sometimes|nullable|numeric',

            // Compras (custo real)
            'compras_itens' => 'sometimes|array',
            'compras_itens.*.compra_item_id' => 'required_with:compras_itens|integer',
            'compras_itens.*.quantidade_base' => 'required_with:compras_itens|numeric|min:0.0001',
        ]);

        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Não aceitar empresa_id do cliente — ignorar se vier
        unset($validated['empresa_id']);
        // SKU é automático; nunca aceitar alteração via cliente.
        unset($validated['sku']);

        // Produto é sempre vendável; ignorar mudanças do cliente.
        unset($validated['vendavel']);

        // Compat: se vier `preco` ou `preco_venda`, tratar como `preco_base`.
        if (array_key_exists('preco', $validated) && ! array_key_exists('preco_base', $validated)) {
            $validated['preco_base'] = $validated['preco'];
        }
        if (array_key_exists('preco_venda', $validated) && ! array_key_exists('preco_base', $validated)) {
            $validated['preco_base'] = $validated['preco_venda'];
        }

        $materiais = null;
        if (array_key_exists('materiais', $validated)) {
            $materiais = $validated['materiais'];
            unset($validated['materiais']);
        }

        $comprasItens = null;
        if (array_key_exists('compras_itens', $validated)) {
            $comprasItens = $validated['compras_itens'];
            unset($validated['compras_itens']);
        }

        try {
            /** @var Produto $produto */
            $produto = DB::transaction(function () use ($produto, $validated, $materiais, $comprasItens, $empresaId) {
                $calc = app(ProdutoVivoCalculoService::class);

                $produto->fill($validated);

                // manter compat: preco_venda acompanha preco_base
                if (array_key_exists('preco_base', $validated)) {
                    $produto->preco_venda = (float) $validated['preco_base'];
                }

                // manter compat: tipo_medida acompanha forma_calculo
                if (array_key_exists('forma_calculo', $validated)) {
                    $produto->tipo_medida = (string) $validated['forma_calculo'];
                }

                // manter status legado sincronizado
                if (array_key_exists('ativo', $validated)) {
                    $produto->status = ((bool) $validated['ativo']) ? 'ativo' : 'inativo';
                }

                $produto->save();

                // reforçar regra
                $produto->vendavel = true;
                $produto->save();

                if (is_array($comprasItens)) {
                    $rowsIn = [];
                    foreach ($comprasItens as $r) {
                        $rowsIn[] = [
                            'compra_item_id' => (int) ($r['compra_item_id'] ?? 0),
                            'quantidade_base' => (float) ($r['quantidade_base'] ?? 0),
                        ];
                    }

                    $calc->validarComprasItens((int) $empresaId, $rowsIn);

                    \App\Models\ProdutoCompraItem::where('empresa_id', (int) $empresaId)
                        ->where('produto_id', (int) $produto->id)
                        ->delete();

                    foreach ($rowsIn as $r) {
                        \App\Models\ProdutoCompraItem::create([
                            'empresa_id' => (int) $empresaId,
                            'produto_id' => (int) $produto->id,
                            'compra_item_id' => (int) $r['compra_item_id'],
                            'quantidade_base' => (float) $r['quantidade_base'],
                        ]);
                    }
                }

                if (is_array($materiais)) {
                    $materiaisIn = [];
                    foreach ($materiais as $mat) {
                        $materialId = (int) ($mat['material_id'] ?? ($mat['material_produto_id'] ?? 0));
                        $materiaisIn[] = [
                            'material_id' => $materialId,
                            'quantidade_base' => (float) ($mat['quantidade_base'] ?? $mat['quantidade'] ?? 0),
                        ];
                    }

                    $calc->validarMateriais($empresaId, (int) $produto->id, $materiaisIn);

                    \App\Models\ProdutoMaterial::where('empresa_id', $empresaId)
                        ->where('produto_id', $produto->id)
                        ->delete();

                    foreach ($materiaisIn as $mat) {
                        \App\Models\ProdutoMaterial::create([
                            'empresa_id' => $empresaId,
                            'produto_id' => $produto->id,
                            'material_id' => (int) $mat['material_id'],
                            'material_produto_id' => (int) $mat['material_id'],
                            'quantidade' => (float) $mat['quantidade_base'],
                            'quantidade_base' => (float) $mat['quantidade_base'],
                        ]);
                    }
                }

                // Recalcular sempre
                $calc->recalcular($produto);

                $produto->load([
                    'materiaisPivot.materialProduto',
                    'comprasItensPivot.compraItem',
                ]);
                return $produto;
            });
        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status >= 600) {
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(['data' => $produto], Response::HTTP_OK);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $produto->ativo = false;
        $produto->status = 'inativo';
        $produto->save();

        return response()->json(['message' => 'Produto marcado como inativo'], Response::HTTP_OK);
    }

    public function recalcularCusto(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        $custoService = new ProdutoCustoService();
        $custo = $custoService->recalcularCusto($produto);

        return response()->json([
            'custo_total' => (float) ($produto->custo_total ?? 0),
            'lucro' => (float) ($produto->lucro ?? 0),
            'margem_percentual' => (float) ($produto->margem_percentual ?? 0),
            // compat
            'custo_calculado' => (float) ($produto->custo_calculado ?? $custo),
            'preco_venda' => (float) ($produto->preco_venda ?? 0),
        ]);
    }

    public function adicionarComponente(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'componente_id' => 'required|exists:componentes,id',
            'quantidade' => 'required|numeric',
            'custo_unitario' => 'required|numeric',
            'descricao' => 'nullable|string',
        ]);

        $empresaId = $request->attributes->get('empresa_id');

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        $componente = Componente::where('id', $validated['componente_id'])
            ->first();

        if (! $componente) {
            return response()->json(['message' => 'Componente não encontrado'], 404);
        }

        // Opcional: garantir que o componente pertença à mesma empresa
        if ($componente->empresa_id !== $empresaId) {
            return response()->json(['message' => 'Componente não pertence à empresa'], 404);
        }

        $pc = new ProdutoComponente();
        $pc->empresa_id = $empresaId;
        $pc->produto_id = $produto->id;
        $pc->componente_id = $validated['componente_id'];
        $pc->quantidade = $validated['quantidade'];
        $pc->custo_unitario = $validated['custo_unitario'];
        $pc->custo_total = (float) $validated['quantidade'] * (float) $validated['custo_unitario'];
        $pc->descricao = $validated['descricao'] ?? null;
        $pc->status = 'ativo';
        $pc->save();

        $custoService = new ProdutoCustoService();
        $custoService->recalcularCusto($produto);

        $pc->load('componente');

        return response()->json($pc, 201);
    }

    public function removerComponente(Request $request, $id, $componenteId): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        $pc = ProdutoComponente::where('empresa_id', $empresaId)
            ->where('produto_id', $produto->id)
            ->where('componente_id', $componenteId)
            ->where('status', 'ativo')
            ->first();

        if (! $pc) {
            return response()->json(['message' => 'Componente do produto não encontrado'], 404);
        }

        $pc->status = 'inativo';
        $pc->save();

        $custoService = new ProdutoCustoService();
        $custoService->recalcularCusto($produto);

        return response()->json(['message' => 'Componente removido da composição']);
    }
}
