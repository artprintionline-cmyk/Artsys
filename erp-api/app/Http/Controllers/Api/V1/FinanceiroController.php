<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ContaReceber;
use App\Models\OrdemServico;
use App\Services\GeradorContaReceberService;
use Carbon\Carbon;

class FinanceiroController extends Controller
{
    /**
     * Listar contas a receber da empresa com filtros opcionais.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');

        $query = ContaReceber::where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('vencimento')) {
            try {
                $v = Carbon::parse($request->input('vencimento'))->toDateString();
                $query->whereDate('vencimento', $v);
            } catch (\Exception $e) {
                return response()->json(['message' => 'vencimento inválido'], 422);
            }
        }

        $parcelas = $query->orderBy('vencimento', 'asc')->get();

        return response()->json($parcelas);
    }

    /**
     * Gerar parcelas para uma Ordem de Serviço.
     *
     * @param Request $request
     * @param int $ordemServicoId
     * @param GeradorContaReceberService $gerador
     */
    public function gerar(Request $request, $ordemServicoId, GeradorContaReceberService $gerador): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $validated = $request->validate([
            'parcelas' => 'required|array',
            'parcelas.*.valor' => 'required|numeric',
            'parcelas.*.vencimento' => 'required|date',
        ]);

        $empresaId = $request->attributes->get('empresa_id');

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $ordemServicoId)
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], 404);
        }

        $parcelas = $validated['parcelas'];

        $gerador->gerarParcelas($ordem, $parcelas);

        $vencimentos = array_map(function ($p) {
            return Carbon::parse($p['vencimento'])->toDateString();
        }, $parcelas);

        $criadas = ContaReceber::where('empresa_id', $empresaId)
            ->where('ordem_servico_id', $ordem->id)
            ->whereIn('vencimento', $vencimentos)
            ->orderBy('vencimento', 'asc')
            ->get();

        return response()->json($criadas, 201);
    }
}
