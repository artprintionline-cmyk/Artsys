<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\OrdemServico;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json([
                'total_os' => 0,
                'em_producao' => 0,
                'faturado' => 0,
                'pendencias' => 0,
            ]);
        }

        $total = OrdemServico::where('empresa_id', $empresaId)->count();

        $emProducao = OrdemServico::where('empresa_id', $empresaId)
            ->where('status_atual', 'producao')
            ->count();

        $faturado = OrdemServico::where('empresa_id', $empresaId)
            ->where('status_atual', 'faturado')
            ->count();

        $pendencias = OrdemServico::where('empresa_id', $empresaId)
            ->whereIn('status_atual', ['pendencia', 'pendente'])
            ->count();

        return response()->json([
            'total_os' => $total,
            'em_producao' => $emProducao,
            'faturado' => $faturado,
            'pendencias' => $pendencias,
        ]);
    }
}
