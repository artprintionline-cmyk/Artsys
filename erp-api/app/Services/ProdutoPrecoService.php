<?php

namespace App\Services;

use App\Models\Produto;

class ProdutoPrecoService
{
    /**
     * Retorna o preço "atual" do produto para consumo em OS/precificação rápida.
     * Não persiste alterações.
     */
    public function precoAtual(Produto $produto): float
    {
        if (isset($produto->preco_base) && $produto->preco_base !== null && (float) $produto->preco_base > 0) {
            return (float) $produto->preco_base;
        }
        if (isset($produto->preco_venda) && $produto->preco_venda !== null && (float) $produto->preco_venda > 0) {
            return (float) $produto->preco_venda;
        }
        if (isset($produto->preco) && $produto->preco !== null) {
            return (float) $produto->preco;
        }
        if (isset($produto->preco_final) && $produto->preco_final !== null) {
            return (float) $produto->preco_final;
        }
        if (isset($produto->preco_manual) && $produto->preco_manual !== null) {
            return (float) $produto->preco_manual;
        }
        return 0.0;
    }

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
        // Produto Vivo: preço de venda é a fonte da verdade.
        // Mantém compatibilidade persistindo também em `preco_final`.
        $valor = (float) $this->precoAtual($produto);

        if (isset($produto->preco_final)) {
            $produto->preco_final = $valor;
            $produto->save();
        }

        return $valor;
    }
}
