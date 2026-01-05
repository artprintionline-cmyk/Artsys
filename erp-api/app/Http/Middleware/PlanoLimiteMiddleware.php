<?php

namespace App\Http\Middleware;

use App\Models\OrdemServico;
use App\Models\User;
use App\Services\SaasAssinaturaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanoLimiteMiddleware
{
    public function handle(Request $request, Closure $next, string $limitKey): Response
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return $next($request);
        }

        /** @var SaasAssinaturaService $saas */
        $saas = app(SaasAssinaturaService::class);

        if ($limitKey === 'os_mes') {
            $max = $saas->limiteInt((int) $empresaId, 'max_os_mes', null);
            if ($max === null || $max <= 0) {
                return $next($request);
            }

            $inicio = now()->copy()->startOfMonth();
            $fim = now()->copy()->endOfMonth();

            $count = OrdemServico::query()
                ->where('empresa_id', (int) $empresaId)
                ->whereBetween('created_at', [$inicio, $fim])
                ->count();

            if ($count >= $max) {
                return response()->json([
                    'message' => 'Limite do plano atingido: máximo de OS por mês.',
                    'code' => 'SAAS_LIMIT_REACHED',
                    'limit' => 'max_os_mes',
                    'max' => $max,
                    'current' => $count,
                ], 403);
            }

            return $next($request);
        }

        if ($limitKey === 'usuarios') {
            // Só bloqueia quando estiver tentando ativar um usuário.
            $status = $request->input('status', null);
            if ($status === null || (bool) $status !== true) {
                return $next($request);
            }

            $max = $saas->limiteInt((int) $empresaId, 'max_usuarios', null);
            if ($max === null || $max <= 0) {
                return $next($request);
            }

            $ativos = User::query()
                ->where('empresa_id', (int) $empresaId)
                ->where('status', true)
                ->count();

            if ($ativos >= $max) {
                return response()->json([
                    'message' => 'Limite do plano atingido: máximo de usuários ativos.',
                    'code' => 'SAAS_LIMIT_REACHED',
                    'limit' => 'max_usuarios',
                    'max' => $max,
                    'current' => $ativos,
                ], 403);
            }

            return $next($request);
        }

        return $next($request);
    }
}
