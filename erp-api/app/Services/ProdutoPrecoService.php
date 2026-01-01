<?php

namespace App\Services;

use App\Models\Produto;

class ProdutoPrecoService
{
    /**
     * Calcula o preço final do produto seguindo as regras:
     * 1) Se `preco_manual` existir -> usar `preco_manual`
     * 2) Senão, se `markup` existir -> `custo_calculado * markup`
     * 3) Senão -> usar `custo_calculado`
     *
     * Persiste o valor em `preco_final` e retorna o valor calculado.
     *
     * @param Produto $produto
     * @return float
     */
    public function calcularPrecoFinal(Produto $produto): float
    {
        // Normalizar valores numéricos
        $precoManual = $produto->preco_manual;
        $markup = $produto->markup;
        $custo = $produto->custo_calculado ?? 0.0;

        if (!is_null($precoManual) && $precoManual !== '') {
            $valor = (float) $precoManual;
        } elseif (!is_null($markup) && $markup !== '') {
            $valor = (float) $custo * (float) $markup;
        } else {
            $valor = (float) $custo;
        }

        $produto->preco_final = $valor;
        $produto->save();

        return $valor;
    }
}
