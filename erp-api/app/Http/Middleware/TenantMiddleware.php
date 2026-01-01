<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Empresa;
use App\Services\Tenant;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && isset($user->empresa_id)) {
            $empresa = Empresa::find($user->empresa_id);
            if (!$empresa || !$empresa->status) {
                return response()->json(['message' => 'Empresa inativa ou não encontrada.'], 403);
            }

            Tenant::setEmpresaId((int) $user->empresa_id);
            $request->attributes->set('empresa_id', $user->empresa_id);
        }

        return $next($request);
    }
}
