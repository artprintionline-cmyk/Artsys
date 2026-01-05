<?php

namespace App\Http\Middleware;

use App\Services\SaasAssinaturaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanoFeatureMiddleware
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return $next($request);
        }

        /** @var SaasAssinaturaService $saas */
        $saas = app(SaasAssinaturaService::class);

        if ($saas->planoPermite((int) $empresaId, $featureKey)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Recurso não disponível no seu plano.',
            'code' => 'SAAS_FEATURE_BLOCKED',
            'feature' => $featureKey,
        ], 403);
    }
}
