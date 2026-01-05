<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EstoqueProduto;
use App\Models\Produto;
use App\Services\AuditoriaService;
use App\Services\EstoqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EstoqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $rows = EstoqueProduto::with(['produto'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('id')
            ->get();

        $data = $rows->map(function (EstoqueProduto $e) {
            return [
                'id' => $e->id,
                'produto' => $e->produto,
                'produto_id' => $e->produto_id,
                'quantidade_atual' => (float) $e->quantidade_atual,
                'estoque_minimo' => (float) $e->estoque_minimo,
                'abaixo_minimo' => (float) $e->quantidade_atual < (float) $e->estoque_minimo,
            ];
        });

        return response()->json(['data' => $data], Response::HTTP_OK);
    }

    public function ajuste(Request $request, EstoqueService $service, AuditoriaService $auditoria): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'produto_id' => 'required|integer',
            'tipo' => 'required|string|in:entrada,saida',
            'quantidade' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|min:3|max:255',
        ]);

        $produto = Produto::where('empresa_id', $empresaId)
            ->where('id', (int) $validated['produto_id'])
            ->first();

        if (! $produto) {
            return response()->json(['message' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $antesEstoque = EstoqueProduto::where('empresa_id', $empresaId)
            ->where('produto_id', (int) $produto->id)
            ->first();

        $antes = [
            'produto_id' => (int) $produto->id,
            'tipo' => (string) $validated['tipo'],
            'quantidade' => (float) $validated['quantidade'],
            'motivo' => (string) $validated['motivo'],
            'quantidade_atual' => $antesEstoque ? (float) $antesEstoque->quantidade_atual : 0.0,
        ];

        try {
            $estoque = $service->ajustar(
                (int) $empresaId,
                (int) $produto->id,
                (string) $validated['tipo'],
                (float) $validated['quantidade'],
                'ajuste',
                null,
                (string) $validated['motivo']
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $estoque->load('produto');

        $depois = [
            'produto_id' => (int) $estoque->produto_id,
            'quantidade_atual' => (float) $estoque->quantidade_atual,
        ];

        $auditoria->log($request, 'adjust', 'estoque', (int) $estoque->produto_id, $antes, $depois);

        return response()->json([
            'data' => [
                'id' => $estoque->id,
                'produto' => $estoque->produto,
                'produto_id' => $estoque->produto_id,
                'quantidade_atual' => (float) $estoque->quantidade_atual,
                'estoque_minimo' => (float) $estoque->estoque_minimo,
                'abaixo_minimo' => (float) $estoque->quantidade_atual < (float) $estoque->estoque_minimo,
            ],
        ], Response::HTTP_OK);
    }
}
