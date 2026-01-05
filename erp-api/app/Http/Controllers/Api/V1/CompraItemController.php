<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompraItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompraItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $tipo = $request->query('tipo');

        $query = CompraItem::query()
            ->where('empresa_id', $empresaId);

        if (is_string($tipo) && $tipo !== '') {
            $query->where('tipo', $tipo);
        }

        $rows = $query->orderBy('nome')->get();

        return response()->json(['data' => $rows], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Item só pode ser criado dentro da compra.',
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $row = CompraItem::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Registro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $row], Response::HTTP_OK);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'message' => 'Item só pode ser alterado dentro da compra.',
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        return response()->json([
            'message' => 'Item só pode ser desativado dentro da compra.',
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
