<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Produto;
use App\Models\Componente;
use App\Models\ProdutoComponente;
use App\Services\ProdutoCustoService;
use App\Services\ProdutoPrecoService;

class ProdutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $produtos = Produto::where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get();

        return response()->json($produtos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string',
            'descricao' => 'nullable|string',
            'tipo_medida' => 'required|string',
            'largura_padrao' => 'nullable|numeric',
            'altura_padrao' => 'nullable|numeric',
            'preco_manual' => 'nullable|numeric',
            'markup' => 'nullable|numeric',
        ]);

        $empresaId = $request->attributes->get('empresa_id');

        $produto = new Produto();
        $produto->empresa_id = $empresaId;
        $produto->nome = $validated['nome'];
        $produto->descricao = $validated['descricao'] ?? null;
        $produto->tipo_medida = $validated['tipo_medida'];
        $produto->largura_padrao = $validated['largura_padrao'] ?? null;
        $produto->altura_padrao = $validated['altura_padrao'] ?? null;
        $produto->preco_manual = $validated['preco_manual'] ?? null;
        $produto->markup = $validated['markup'] ?? null;
        $produto->status = 'ativo';
        $produto->custo_calculado = 0;
        $produto->preco_final = 0;
        $produto->save();

        return response()->json($produto, 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $produto = Produto::with(['produtoComponentes.componente'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        return response()->json($produto);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'sometimes|string',
            'descricao' => 'sometimes|nullable|string',
            'tipo_medida' => 'sometimes|string',
            'largura_padrao' => 'sometimes|nullable|numeric',
            'altura_padrao' => 'sometimes|nullable|numeric',
            'preco_manual' => 'sometimes|nullable|numeric',
            'markup' => 'sometimes|nullable|numeric',
        ]);

        $empresaId = $request->attributes->get('empresa_id');

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        // Não aceitar empresa_id do cliente — ignorar se vier
        unset($validated['empresa_id']);

        $produto->fill($validated);
        $produto->save();

        return response()->json($produto);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        $produto->status = 'inativo';
        $produto->save();

        return response()->json(['message' => 'Produto marcado como inativo']);
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
        $precoService = new ProdutoPrecoService();

        $custo = $custoService->recalcularCusto($produto);
        $preco = $precoService->calcularPrecoFinal($produto);

        return response()->json([
            'custo_calculado' => $custo,
            'preco_final' => $preco,
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
        $precoService = new ProdutoPrecoService();

        $custoService->recalcularCusto($produto);
        $precoService->calcularPrecoFinal($produto);

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
        $precoService = new ProdutoPrecoService();

        $custoService->recalcularCusto($produto);
        $precoService->calcularPrecoFinal($produto);

        return response()->json(['message' => 'Componente removido da composição']);
    }
}
