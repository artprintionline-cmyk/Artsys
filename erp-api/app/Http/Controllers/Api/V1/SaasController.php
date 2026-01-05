<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Plano;
use App\Models\SaasPagamento;
use App\Services\SaasAssinaturaService;
use App\Services\SaasAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SaasController extends Controller
{
    public function planos(): JsonResponse
    {
        $planos = Plano::query()
            ->where('ativo', true)
            ->orderBy('preco')
            ->get()
            ->map(fn (Plano $p) => [
                'id' => (int) $p->id,
                'nome' => (string) $p->nome,
                'preco' => (float) $p->preco,
                'limites' => is_array($p->limites) ? $p->limites : [],
            ]);

        return response()->json(['data' => $planos], Response::HTTP_OK);
    }

    public function assinatura(Request $request, SaasAssinaturaService $saas): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $status = $saas->statusAcesso((int) $empresaId);

        return response()->json(['data' => $status], Response::HTTP_OK);
    }

    public function simularPagamento(Request $request, SaasAuditoriaService $auditoria): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'plano_id' => 'required|integer|exists:planos,id',
            'meses' => 'sometimes|integer|min:1|max:24',
            'referencia' => 'nullable|string|max:190',
        ]);

        $meses = isset($validated['meses']) ? (int) $validated['meses'] : 1;
        $plano = Plano::find((int) $validated['plano_id']);
        if (! $plano || ! $plano->ativo) {
            return response()->json(['message' => 'Plano inválido ou inativo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inicio = now();
        $fim = now()->addMonthsNoOverflow($meses);

        $assinatura = Assinatura::updateOrCreate(
            ['empresa_id' => (int) $empresaId],
            [
                'plano_id' => (int) $plano->id,
                'status' => 'ativa',
                'inicio' => $inicio,
                'fim' => $fim,
            ]
        );

        SaasPagamento::create([
            'assinatura_id' => (int) $assinatura->id,
            'valor' => (float) $plano->preco,
            'status' => 'pago',
            'metodo' => 'simulada',
            'referencia' => $validated['referencia'] ?? null,
            'pago_em' => now(),
            'payload' => [
                'meses' => $meses,
            ],
        ]);

        $auditoria->log('assinatura.pagamento_simulado', (int) $empresaId, $user ? (int) $user->id : null, [
            'plano_id' => (int) $plano->id,
            'meses' => $meses,
            'inicio' => $inicio->toIso8601String(),
            'fim' => $fim->toIso8601String(),
        ]);

        return response()->json(['data' => [
            'assinatura_id' => (int) $assinatura->id,
            'status' => (string) $assinatura->status,
            'inicio' => $assinatura->inicio?->toIso8601String(),
            'fim' => $assinatura->fim?->toIso8601String(),
            'plano_id' => (int) $assinatura->plano_id,
        ]], Response::HTTP_OK);
    }

    public function setStatus(Request $request, SaasAuditoriaService $auditoria): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        $user = $request->user();

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:trial,ativa,suspensa,cancelada',
        ]);

        $assinatura = Assinatura::where('empresa_id', (int) $empresaId)->first();
        if (! $assinatura) {
            return response()->json(['message' => 'Assinatura não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $antes = (string) $assinatura->status;
        $assinatura->status = (string) $validated['status'];
        $assinatura->save();

        $auditoria->log('assinatura.status_alterado', (int) $empresaId, $user ? (int) $user->id : null, [
            'antes' => $antes,
            'depois' => (string) $assinatura->status,
        ]);

        return response()->json(['data' => [
            'status' => (string) $assinatura->status,
        ]], Response::HTTP_OK);
    }
}
