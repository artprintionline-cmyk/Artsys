<?php

namespace App\Http\Middleware;

use App\Services\SaasAssinaturaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanoMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return $next($request);
        }

        /** @var SaasAssinaturaService $saas */
        $saas = app(SaasAssinaturaService::class);

        // Sempre permitir leitura.
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Permitir logout mesmo em modo leitura.
        if ($request->is('api/v1/auth/logout')) {
            return $next($request);
        }

        $status = $saas->statusAcesso((int) $empresaId);
        if (! $status['read_only']) {
            return $next($request);
        }

        return response()->json([
            'message' => (string) ($status['motivo'] ?? 'Acesso somente leitura.'),
            'code' => 'SAAS_READ_ONLY',
            'assinatura' => [
                'status' => $status['status'],
                'expires_at' => $status['expires_at'],
                'trial_expired' => (bool) $status['trial_expired'],
                'plano' => $status['plano'],
            ],
        ], 403);
    }
}
