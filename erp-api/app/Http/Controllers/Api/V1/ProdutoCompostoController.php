<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use App\Models\ProdutoComposto;
use App\Models\ProdutoCompostoItem;
use App\Services\ProdutoCompostoPrecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProdutoCompostoController extends Controller
{
    public function index(Request $request, ProdutoCompostoPrecoService $precoService): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $rows = ProdutoComposto::with(['itens.produto'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('id')
            ->get();

        $data = $rows->map(function (ProdutoComposto $c) use ($precoService) {
            return [
                'id' => $c->id,
                'nome' => $c->nome,
                'descricao' => $c->descricao,
                'preco_base' => $c->preco_base,
                'preco_calculado' => $precoService->precoEfetivo($c),
                'status' => $c->status,
                'itens' => $c->itens->map(fn ($it) => [
                    'id' => $it->id,
                    'produto_id' => $it->produto_id,
                    'produto' => $it->produto,
                    'quantidade' => (float) $it->quantidade,
                ])->values(),
            ];
        });

        return response()->json(['data' => $data], Response::HTTP_OK);
    }

    public function show(Request $request, int $id, ProdutoCompostoPrecoService $precoService): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $c = ProdutoComposto::with(['itens.produto'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $c) {
            return response()->json(['message' => 'Produto composto não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'id' => $c->id,
                'nome' => $c->nome,
                'descricao' => $c->descricao,
                'preco_base' => $c->preco_base,
                'preco_calculado' => $precoService->precoEfetivo($c),
                'status' => $c->status,
                'itens' => $c->itens->map(fn ($it) => [
                    'id' => $it->id,
                    'produto_id' => $it->produto_id,
                    'produto' => $it->produto,
                    'quantidade' => (float) $it->quantidade,
                ])->values(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request, ProdutoCompostoPrecoService $precoService): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco_base' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:ativo,inativo',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|numeric|min:0.01',
        ]);

        foreach ($validated['itens'] as $it) {
            $ok = Produto::where('empresa_id', $empresaId)
                ->where('id', (int) $it['produto_id'])
                ->exists();
            if (! $ok) {
                return response()->json(['message' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
            }
        }

        try {
            $c = DB::transaction(function () use ($empresaId, $validated, $precoService) {
                $status = $validated['status'] ?? 'ativo';

                $c = ProdutoComposto::create([
                    'empresa_id' => $empresaId,
                    'nome' => $validated['nome'],
                    'descricao' => $validated['descricao'] ?? null,
                    'preco_base' => $validated['preco_base'] ?? null,
                    'status' => $status,
                ]);

                foreach ($validated['itens'] as $it) {
                    ProdutoCompostoItem::create([
                        'produto_composto_id' => $c->id,
                        'produto_id' => (int) $it['produto_id'],
                        'quantidade' => (float) $it['quantidade'],
                    ]);
                }

                $c->load(['itens.produto']);

                if ($status === 'ativo') {
                    $precoEfetivo = (float) $precoService->precoEfetivo($c);
                    if ($precoEfetivo <= 0) {
                        throw new \RuntimeException('Preço calculado do composto deve ser maior que zero.');
                    }
                }

                return $c;
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'id' => $c->id,
                'nome' => $c->nome,
                'descricao' => $c->descricao,
                'preco_base' => $c->preco_base,
                'preco_calculado' => $precoService->precoEfetivo($c),
                'status' => $c->status,
                'itens' => $c->itens->map(fn ($it) => [
                    'id' => $it->id,
                    'produto_id' => $it->produto_id,
                    'produto' => $it->produto,
                    'quantidade' => (float) $it->quantidade,
                ])->values(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id, ProdutoCompostoPrecoService $precoService): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $c = ProdutoComposto::where('empresa_id', $empresaId)->where('id', $id)->first();
        if (! $c) {
            return response()->json(['message' => 'Produto composto não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'descricao' => 'sometimes|nullable|string',
            'preco_base' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|string|in:ativo,inativo',
            'itens' => 'sometimes|array|min:1',
            'itens.*.produto_id' => 'required_with:itens|integer',
            'itens.*.quantidade' => 'required_with:itens|numeric|min:0.01',
        ]);

        try {
            $c = DB::transaction(function () use ($c, $empresaId, $validated, $precoService) {
                if (array_key_exists('nome', $validated)) $c->nome = $validated['nome'];
                if (array_key_exists('descricao', $validated)) $c->descricao = $validated['descricao'];
                if (array_key_exists('preco_base', $validated)) $c->preco_base = $validated['preco_base'];
                if (array_key_exists('status', $validated)) $c->status = $validated['status'];
                $c->save();

                if (array_key_exists('itens', $validated)) {
                    foreach ($validated['itens'] as $it) {
                        $ok = Produto::where('empresa_id', $empresaId)
                            ->where('id', (int) $it['produto_id'])
                            ->exists();
                        if (! $ok) {
                            throw new \RuntimeException('Produto não encontrado.');
                        }
                    }

                    ProdutoCompostoItem::where('produto_composto_id', $c->id)->delete();

                    foreach ($validated['itens'] as $it) {
                        ProdutoCompostoItem::create([
                            'produto_composto_id' => $c->id,
                            'produto_id' => (int) $it['produto_id'],
                            'quantidade' => (float) $it['quantidade'],
                        ]);
                    }
                }

                $c->load(['itens.produto']);

                if ($c->status === 'ativo') {
                    $precoEfetivo = (float) $precoService->precoEfetivo($c);
                    if ($precoEfetivo <= 0) {
                        throw new \RuntimeException('Preço calculado do composto deve ser maior que zero.');
                    }
                }

                return $c;
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'id' => $c->id,
                'nome' => $c->nome,
                'descricao' => $c->descricao,
                'preco_base' => $c->preco_base,
                'preco_calculado' => $precoService->precoEfetivo($c),
                'status' => $c->status,
                'itens' => $c->itens->map(fn ($it) => [
                    'id' => $it->id,
                    'produto_id' => $it->produto_id,
                    'produto' => $it->produto,
                    'quantidade' => (float) $it->quantidade,
                ])->values(),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $c = ProdutoComposto::where('empresa_id', $empresaId)->where('id', $id)->first();
        if (! $c) {
            return response()->json(['message' => 'Produto composto não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $c->status = 'inativo';
        $c->save();

        return response()->json(['ok' => true], Response::HTTP_OK);
    }
}
