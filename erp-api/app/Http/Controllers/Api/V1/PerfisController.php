<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PerfisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $perfis = Perfil::with(['permissoes'])
            ->where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get()
            ->map(function (Perfil $p) {
                return [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'permissoes' => $p->permissoes->pluck('chave')->values(),
                ];
            });

        return response()->json(['data' => $perfis], Response::HTTP_OK);
    }

    public function atualizarPermissoes(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $perfil = Perfil::with('permissoes')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'permissoes' => 'required|array',
            'permissoes.*' => 'required|string',
        ]);

        $antes = $perfil->permissoes->pluck('chave')->values()->all();

        $keys = array_values(array_unique(array_map('strval', $validated['permissoes'])));
        $permissoes = Permissao::whereIn('chave', $keys)->get();
        $perfil->permissoes()->sync($permissoes->pluck('id')->all());
        $perfil->load('permissoes');

        $depois = $perfil->permissoes->pluck('chave')->values()->all();

        $auditoria->log($request, 'update', 'permissoes', (int) $perfil->id, ['permissoes' => $antes], ['permissoes' => $depois]);

        return response()->json([
            'data' => [
                'id' => $perfil->id,
                'nome' => $perfil->nome,
                'permissoes' => $perfil->permissoes->pluck('chave')->values(),
            ],
        ], Response::HTTP_OK);
    }
}
