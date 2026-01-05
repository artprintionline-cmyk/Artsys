<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UsuariosController extends Controller
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

        $usuarios = User::with(['perfil'])
            ->where('empresa_id', $empresaId)
            ->orderBy('name')
            ->get()
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'status' => (bool) $u->status,
                    'perfil' => $u->perfil ? ['id' => $u->perfil->id, 'nome' => $u->perfil->nome] : null,
                ];
            });

        return response()->json(['data' => $usuarios], Response::HTTP_OK);
    }

    public function update(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'perfil_id' => 'sometimes|nullable|integer',
            'status' => 'sometimes|boolean',
        ]);

        $u = User::with('perfil')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $u) {
            return response()->json(['message' => 'Usuário não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $antes = [
            'status' => (bool) $u->status,
            'perfil_id' => $u->perfil_id,
        ];

        if (array_key_exists('status', $validated)) {
            $u->status = (bool) $validated['status'];
        }

        if (array_key_exists('perfil_id', $validated)) {
            $perfilId = $validated['perfil_id'];
            if ($perfilId === null) {
                $u->perfil_id = null;
            } else {
                $perfil = Perfil::where('empresa_id', $empresaId)->where('id', (int) $perfilId)->first();
                if (! $perfil) {
                    return response()->json(['message' => 'Perfil inválido'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $u->perfil_id = (int) $perfil->id;
            }
        }

        $u->save();
        $u->load('perfil');

        $depois = [
            'status' => (bool) $u->status,
            'perfil_id' => $u->perfil_id,
        ];

        $auditoria->log($request, 'update', 'usuario', (int) $u->id, $antes, $depois);

        return response()->json([
            'data' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => (bool) $u->status,
                'perfil' => $u->perfil ? ['id' => $u->perfil->id, 'nome' => $u->perfil->nome] : null,
            ],
        ], Response::HTTP_OK);
    }
}
