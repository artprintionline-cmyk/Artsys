<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\FinanceiroGeradoEvent;
use App\Events\PagamentoConfirmadoEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ContaReceber;
use App\Models\FinanceiroLancamento;
use App\Models\OrdemServico;
use App\Models\Cliente;
use App\Services\GeradorContaReceberService;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Http\Response;

class FinanceiroController extends Controller
{
    /**
     * Listar lançamentos financeiros (financeiro_lancamentos) da empresa.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');

        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $query = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $empresaId)
            ->where('status', '!=', 'cancelado');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('data_vencimento')) {
            try {
                $v = Carbon::parse($request->input('data_vencimento'))->toDateString();
                $query->whereDate('data_vencimento', $v);
            } catch (\Exception $e) {
                return response()->json(['message' => 'data_vencimento inválida'], 422);
            }
        }

        $lancamentos = $query->orderBy('data_vencimento', 'asc')->get();

        $payload = $lancamentos->map(function (FinanceiroLancamento $l) {
            return [
                'id' => $l->id,
                'cliente' => $l->cliente,
                'ordem_servico' => $l->ordemServico,
                'tipo' => $l->tipo,
                'descricao' => $l->descricao,
                'valor' => (float) $l->valor,
                'status' => $l->status,
                'data_vencimento' => $l->data_vencimento,
                'data_pagamento' => $l->data_pagamento,
                'created_at' => $l->created_at,
            ];
        });

        return response()->json(['data' => $payload], Response::HTTP_OK);
    }

    /**
     * Criar lançamento automaticamente a partir de uma OS.
     * Nunca aceitar empresa_id/cliente_id/valor no request.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }
        if ($request->has('cliente_id')) {
            return response()->json(['message' => 'cliente_id não é permitido no request'], 422);
        }
        if ($request->has('valor')) {
            return response()->json(['message' => 'valor não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'ordem_servico_id' => 'required|integer',
            'tipo' => 'sometimes|string|in:receber,pagar',
            'descricao' => 'sometimes|string',
            'data_vencimento' => 'required|date',
        ]);

        $ordem = OrdemServico::where('empresa_id', $empresaId)
            ->where('id', $validated['ordem_servico_id'])
            ->first();

        if (! $ordem) {
            return response()->json(['message' => 'Ordem de serviço não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->where('id', $ordem->cliente_id)
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ((float) $ordem->valor_total <= 0) {
            return response()->json(['message' => 'A OS precisa ter valor total maior que zero.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $jaLancado = FinanceiroLancamento::where('empresa_id', $empresaId)
            ->where('ordem_servico_id', $ordem->id)
            ->where('status', '!=', 'cancelado')
            ->exists();

        if ($jaLancado) {
            return response()->json(['message' => 'Já existe um lançamento financeiro ativo para esta OS.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tipo = $validated['tipo'] ?? 'receber';
        $descricao = $validated['descricao'] ?? ("OS {$ordem->numero}");

        $lancamento = FinanceiroLancamento::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordem->id,
            'cliente_id' => $cliente->id,
            'tipo' => $tipo,
            'descricao' => $descricao,
            'valor' => (float) $ordem->valor_total,
            'status' => 'pendente',
            'data_vencimento' => Carbon::parse($validated['data_vencimento'])->toDateString(),
            'data_pagamento' => null,
        ]);

        $lancamento->load(['cliente', 'ordemServico']);

        // Automações por evento (best-effort)
        try {
            $user = $request->user();
            event(new FinanceiroGeradoEvent(
                (int) $empresaId,
                (int) $lancamento->id,
                $lancamento->ordem_servico_id ? (int) $lancamento->ordem_servico_id : null,
                $lancamento->cliente_id ? (int) $lancamento->cliente_id : null,
                $user ? (int) $user->id : 0
            ));
        } catch (\Throwable $e) {
            // não bloquear
        }

        return response()->json([
            'data' => [
                'id' => $lancamento->id,
                'cliente' => $lancamento->cliente,
                'ordem_servico' => $lancamento->ordemServico,
                'tipo' => $lancamento->tipo,
                'descricao' => $lancamento->descricao,
                'valor' => (float) $lancamento->valor,
                'status' => $lancamento->status,
                'data_vencimento' => $lancamento->data_vencimento,
                'data_pagamento' => $lancamento->data_pagamento,
                'created_at' => $lancamento->created_at,
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $l = FinanceiroLancamento::with(['cliente', 'ordemServico'])
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $l) {
            return response()->json(['message' => 'Lançamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'id' => $l->id,
                'cliente' => $l->cliente,
                'ordem_servico' => $l->ordemServico,
                'tipo' => $l->tipo,
                'descricao' => $l->descricao,
                'valor' => (float) $l->valor,
                'status' => $l->status,
                'data_vencimento' => $l->data_vencimento,
                'data_pagamento' => $l->data_pagamento,
                'created_at' => $l->created_at,
                'updated_at' => $l->updated_at,
            ],
        ], Response::HTTP_OK);
    }

    public function update(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }
        if ($request->has('cliente_id') || $request->has('ordem_servico_id') || $request->has('valor')) {
            return response()->json(['message' => 'Edição manual de cliente/OS/valor não é permitida'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pendente,pago,cancelado',
            'data_pagamento' => 'sometimes|nullable|date',
        ]);

        $l = FinanceiroLancamento::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $l) {
            return response()->json(['message' => 'Lançamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        if ((string) $l->status === 'cancelado' && (string) $validated['status'] !== 'cancelado') {
            return response()->json(['message' => 'Lançamento cancelado não pode ser reaberto.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ((string) $l->status === 'pago' && (string) $validated['status'] !== 'pago') {
            return response()->json(['message' => 'Lançamento pago não pode voltar para pendente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $antes = [
            'status' => (string) $l->status,
            'data_pagamento' => $l->data_pagamento,
        ];

        $status = (string) $validated['status'];
        $l->status = $status;

        if ($status === 'pago') {
            $l->data_pagamento = array_key_exists('data_pagamento', $validated)
                ? ($validated['data_pagamento'] ? Carbon::parse($validated['data_pagamento'])->toDateString() : null)
                : now()->toDateString();
        }

        if ($status === 'pendente') {
            $l->data_pagamento = null;
        }

        if ($status === 'cancelado') {
            $l->data_pagamento = null;
        }

        $l->save();
        $l->load(['cliente', 'ordemServico']);

        $depois = [
            'status' => (string) $l->status,
            'data_pagamento' => $l->data_pagamento,
        ];

        $auditoria->log($request, 'status_change', 'financeiro', (int) $l->id, $antes, $depois);

        // Automações por evento: pagamento confirmado
        try {
            if ((string) ($antes['status'] ?? '') !== 'pago' && $status === 'pago') {
                event(new PagamentoConfirmadoEvent(
                    (int) $empresaId,
                    (int) $l->id,
                    $l->ordem_servico_id ? (int) $l->ordem_servico_id : null,
                    $l->cliente_id ? (int) $l->cliente_id : null,
                    'manual'
                ));
            }
        } catch (\Throwable $e) {
            // não bloquear
        }

        return response()->json([
            'data' => [
                'id' => $l->id,
                'cliente' => $l->cliente,
                'ordem_servico' => $l->ordemServico,
                'tipo' => $l->tipo,
                'descricao' => $l->descricao,
                'valor' => (float) $l->valor,
                'status' => $l->status,
                'data_vencimento' => $l->data_vencimento,
                'data_pagamento' => $l->data_pagamento,
                'updated_at' => $l->updated_at,
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, $id, AuditoriaService $auditoria): JsonResponse
    {
        if ($request->has('empresa_id')) {
            return response()->json(['message' => 'empresa_id não é permitido no request'], 422);
        }

        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $l = FinanceiroLancamento::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $l) {
            return response()->json(['message' => 'Lançamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $antes = [
            'status' => (string) $l->status,
            'data_pagamento' => $l->data_pagamento,
        ];

        $l->status = 'cancelado';
        $l->data_pagamento = null;
        $l->save();

        $depois = [
            'status' => (string) $l->status,
            'data_pagamento' => $l->data_pagamento,
        ];

        $auditoria->log($request, 'cancel', 'financeiro', (int) $l->id, $antes, $depois);

        return response()->json(['message' => 'Lançamento cancelado'], Response::HTTP_OK);
    }

    /**
     * Gerar parcelas para uma Ordem de Serviço.
     *
     * @param Request $request
     * @param int $ordemServicoId
     * @param GeradorContaReceberService $gerador
     */
    public function gerar(Request $request, $ordemServicoId, GeradorContaReceberService $gerador, AuditoriaService $auditoria): JsonResponse
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

        $antes = [
            'ordem_servico_id' => (int) $ordem->id,
            'parcelas' => array_map(function ($p) {
                return [
                    'valor' => (float) ($p['valor'] ?? 0),
                    'vencimento' => isset($p['vencimento']) ? Carbon::parse($p['vencimento'])->toDateString() : null,
                ];
            }, $parcelas),
        ];

        try {
            $gerador->gerarParcelas($ordem, $parcelas);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $vencimentos = array_map(function ($p) {
            return Carbon::parse($p['vencimento'])->toDateString();
        }, $parcelas);

        $criadas = ContaReceber::where('empresa_id', $empresaId)
            ->where('ordem_servico_id', $ordem->id)
            ->whereIn('vencimento', $vencimentos)
            ->orderBy('vencimento', 'asc')
            ->get();

        $depois = [
            'criadas_ids' => $criadas->pluck('id')->map(fn ($v) => (int) $v)->values()->all(),
            'criadas_total' => (int) $criadas->count(),
        ];

        $auditoria->log($request, 'create', 'financeiro_parcelas', (int) $ordem->id, $antes, $depois);

        return response()->json($criadas, 201);
    }
}
