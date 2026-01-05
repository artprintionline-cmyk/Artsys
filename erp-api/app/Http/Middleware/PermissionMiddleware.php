<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Uso: ->middleware('perm:clientes.view')
     * Aceita múltiplas chaves separadas por | (OR).
     */
    public function handle(Request $request, Closure $next, string $required): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if ($empresaId && isset($user->empresa_id) && (int) $user->empresa_id !== (int) $empresaId) {
            return response()->json(['message' => 'Acesso negado (empresa)'], 403);
        }

        $perfil = $user->perfil()->first();
        if ($perfil && strtolower((string) $perfil->nome) === 'admin') {
            return $next($request);
        }

        $have = method_exists($user, 'permissoesChaves') ? $user->permissoesChaves() : [];
        if (in_array('*', $have, true)) {
            return $next($request);
        }

        $requiredAny = array_filter(array_map('trim', explode('|', $required)));
        foreach ($requiredAny as $key) {
            if (in_array($key, $have, true)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Sem permissão'], 403);
    }
}
