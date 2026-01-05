<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EstoqueInsumo;
use App\Models\Insumo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InsumoController extends Controller
{
    private function calcularCustoUnitarioMaterial(array $validated): float
    {
        $tipo = (string) ($validated['tipo_embalagem'] ?? '');
        $valorEmbalagem = (float) ($validated['valor_embalagem'] ?? 0);

        if ($valorEmbalagem <= 0) {
            return 0.0;
        }

        if (in_array($tipo, ['Pacote', 'Caixa', 'Unidade'], true)) {
            $qtd = (float) ($validated['quantidade_por_embalagem'] ?? 0);
            if ($qtd <= 0) {
                return 0.0;
            }
            return round($valorEmbalagem / $qtd, 4);
        }

        if (in_array($tipo, ['Kg', 'Litro'], true)) {
            $rend = (float) ($validated['rendimento_total'] ?? 0);
            if ($rend <= 0) {
                return 0.0;
            }
            return round($valorEmbalagem / $rend, 4);
        }

        return 0.0;
    }

    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $insumos = Insumo::query()
            ->where('empresa_id', $empresaId)
            ->with(['estoque'])
            ->orderBy('nome')
            ->get();

        return response()->json(['data' => $insumos], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $isMaterialV11 = $request->has('tipo_embalagem');

        $validated = $request->validate($isMaterialV11 ? [
            'nome' => 'required|string',
            'sku' => 'nullable|string',
            'unidade_consumo' => 'required|string|max:20',
            'tipo_embalagem' => 'required|string|in:Pacote,Caixa,Unidade,Kg,Litro',
            'valor_embalagem' => 'required|numeric|min:0.0001',
            'quantidade_por_embalagem' => 'required_if:tipo_embalagem,Pacote,Caixa,Unidade|nullable|numeric|min:0.0001',
            'rendimento_total' => 'required_if:tipo_embalagem,Kg,Litro|nullable|numeric|min:0.0001',
            'ativo' => 'sometimes|boolean',
        ] : [
            'nome' => 'required|string',
            'sku' => 'nullable|string',
            'custo_unitario' => 'required|numeric|min:0',
            'unidade_medida' => 'required|string|max:20',
            'estoque_atual' => 'sometimes|numeric|min:0',
            'controla_estoque' => 'sometimes|boolean',
            'ativo' => 'sometimes|boolean',
        ]);

        $insumo = new Insumo();
        $insumo->empresa_id = (int) $empresaId;
        $insumo->nome = (string) $validated['nome'];
        $insumo->sku = array_key_exists('sku', $validated) ? ($validated['sku'] !== null ? trim((string) $validated['sku']) : null) : null;

        if ($isMaterialV11) {
            $unidadeConsumo = (string) $validated['unidade_consumo'];
            $tipo = (string) $validated['tipo_embalagem'];
            if ($tipo === 'Kg') {
                $unidadeConsumo = 'g';
            }
            if ($tipo === 'Litro') {
                $unidadeConsumo = 'ml';
            }

            $insumo->unidade_medida = $unidadeConsumo;
            $insumo->custo_unitario = $this->calcularCustoUnitarioMaterial($validated);
            $insumo->controla_estoque = false;
        } else {
            $insumo->custo_unitario = (float) $validated['custo_unitario'];
            $insumo->unidade_medida = (string) $validated['unidade_medida'];
            $insumo->controla_estoque = (bool) ($validated['controla_estoque'] ?? true);
        }

        $insumo->ativo = (bool) ($validated['ativo'] ?? true);
        $insumo->save();

        if (array_key_exists('estoque_atual', $validated)) {
            $estoque = EstoqueInsumo::firstOrCreate(
                ['empresa_id' => (int) $empresaId, 'insumo_id' => (int) $insumo->id],
                ['quantidade_atual' => 0, 'estoque_minimo' => 0]
            );
            $estoque->quantidade_atual = (float) $validated['estoque_atual'];
            $estoque->save();
        }

        $insumo->load(['estoque']);

        return response()->json([
            'message' => 'Registro salvo com sucesso',
            'data' => $insumo,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $insumo = Insumo::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->with(['estoque'])
            ->first();

        if (! $insumo) {
            return response()->json(['message' => 'Insumo não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $insumo], Response::HTTP_OK);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $insumo = Insumo::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $insumo) {
            return response()->json(['message' => 'Insumo não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $isMaterialV11 = $request->has('tipo_embalagem');

        $validated = $request->validate($isMaterialV11 ? [
            'nome' => 'sometimes|string',
            'sku' => 'sometimes|nullable|string',
            'unidade_consumo' => 'sometimes|string|max:20',
            'tipo_embalagem' => 'required|string|in:Pacote,Caixa,Unidade,Kg,Litro',
            'valor_embalagem' => 'required|numeric|min:0.0001',
            'quantidade_por_embalagem' => 'required_if:tipo_embalagem,Pacote,Caixa,Unidade|nullable|numeric|min:0.0001',
            'rendimento_total' => 'required_if:tipo_embalagem,Kg,Litro|nullable|numeric|min:0.0001',
            'ativo' => 'sometimes|boolean',
        ] : [
            'nome' => 'sometimes|string',
            'sku' => 'sometimes|nullable|string',
            'custo_unitario' => 'sometimes|numeric|min:0',
            'unidade_medida' => 'sometimes|string|max:20',
            'estoque_atual' => 'sometimes|numeric|min:0',
            'controla_estoque' => 'sometimes|boolean',
            'ativo' => 'sometimes|boolean',
        ]);

        unset($validated['empresa_id']);

        $estoqueAtual = null;
        if (array_key_exists('estoque_atual', $validated)) {
            $estoqueAtual = (float) $validated['estoque_atual'];
            unset($validated['estoque_atual']);
        }

        if ($isMaterialV11) {
            $unidadeConsumo = (string) ($validated['unidade_consumo'] ?? $insumo->unidade_medida);
            $tipo = (string) $validated['tipo_embalagem'];
            if ($tipo === 'Kg') {
                $unidadeConsumo = 'g';
            }
            if ($tipo === 'Litro') {
                $unidadeConsumo = 'ml';
            }

            $validated['unidade_medida'] = $unidadeConsumo;
            $validated['custo_unitario'] = $this->calcularCustoUnitarioMaterial($validated);
            $validated['controla_estoque'] = false;

            unset($validated['unidade_consumo']);
            unset($validated['tipo_embalagem']);
            unset($validated['valor_embalagem']);
            unset($validated['quantidade_por_embalagem']);
            unset($validated['rendimento_total']);
        }

        $insumo->fill($validated);
        $insumo->save();

        if ($estoqueAtual !== null) {
            $estoque = EstoqueInsumo::firstOrCreate(
                ['empresa_id' => (int) $empresaId, 'insumo_id' => (int) $insumo->id],
                ['quantidade_atual' => 0, 'estoque_minimo' => 0]
            );
            $estoque->quantidade_atual = (float) $estoqueAtual;
            $estoque->save();
        }

        $insumo->load(['estoque']);

        return response()->json([
            'message' => 'Registro salvo com sucesso',
            'data' => $insumo,
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('empresa_id');
        if (! $empresaId) {
            return response()->json(['message' => 'Empresa não informada.'], Response::HTTP_BAD_REQUEST);
        }

        $insumo = Insumo::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (! $insumo) {
            return response()->json(['message' => 'Insumo não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $insumo->ativo = false;
        $insumo->save();

        $insumo->load(['estoque']);

        return response()->json(['message' => 'Insumo marcado como inativo'], Response::HTTP_OK);
    }
}
