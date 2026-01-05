<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Cliente;
use App\Models\FinanceiroLancamento;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get();

        return response()->json(['data' => $clientes], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'nome' => 'required|string',
            'telefone' => 'nullable|string',
            'email' => 'nullable|email',
            'observacoes' => 'nullable|string',
        ]);

        $cliente = Cliente::create([
            'empresa_id' => $empresaId,
            'nome' => $validated['nome'],
            'telefone' => $validated['telefone'] ?? null,
            'email' => $validated['email'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
            'status' => 'ativo',
        ]);

        return response()->json(['data' => $cliente], Response::HTTP_CREATED);
    }

    public function show(Request $request, $id)
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $cliente], Response::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'nome' => 'sometimes|string',
            'telefone' => 'sometimes|string',
            'email' => 'sometimes|nullable|email',
            'observacoes' => 'sometimes|nullable|string',
            'status' => 'sometimes|string',
        ]);

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Never allow empresa_id from the request to be mass-assigned
        unset($validated['empresa_id']);

        $cliente->fill($validated);
        $cliente->save();

        return response()->json(['data' => $cliente], Response::HTTP_OK);
    }

    public function destroy(Request $request, $id)
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $cliente->status = 'inativo';
        $cliente->save();

        return response()->json(['message' => 'Cliente marcado como inativo.'], Response::HTTP_OK);
    }

    public function financeiroResumo(Request $request, $id)
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $totais = FinanceiroLancamento::query()
            ->selectRaw("status, COALESCE(SUM(valor), 0) as total")
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $cliente->id)
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $ultimos = FinanceiroLancamento::with(['ordemServico'])
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (FinanceiroLancamento $l) {
                return [
                    'id' => $l->id,
                    'descricao' => $l->descricao,
                    'valor' => $l->valor,
                    'status' => $l->status,
                    'data_vencimento' => $l->data_vencimento,
                    'data_pagamento' => $l->data_pagamento,
                    'created_at' => (string) $l->created_at,
                    'ordem_servico' => $l->ordemServico ? [
                        'id' => $l->ordemServico->id,
                        'numero' => $l->ordemServico->numero ?? null,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => [
                'cliente' => ['id' => $cliente->id, 'nome' => $cliente->nome],
                'totais' => [
                    'pendente' => (float) ($totais['pendente'] ?? 0),
                    'pago' => (float) ($totais['pago'] ?? 0),
                    'cancelado' => (float) ($totais['cancelado'] ?? 0),
                ],
                'ultimos' => $ultimos,
            ],
        ], Response::HTTP_OK);
    }
}
