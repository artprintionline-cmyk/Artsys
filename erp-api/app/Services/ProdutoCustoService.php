<?php

namespace App\Services;

use App\Models\Produto;

class ProdutoCustoService
{
    /**
     * Recalcula o custo total do produto somando o campo `custo_total`
     * de todos os `produto_componentes` ativos e persiste em
     * `produto->custo_calculado`.
     *
     * @param  Produto  $produto
     * @return float
     */
    public function recalcularCusto(Produto $produto): float
    {
        $total = $produto->produtoComponentes()
            ->where('status', 'ativo')
            ->sum('custo_total');

        $valor = (float) $total;

        $produto->custo_calculado = $valor;
        $produto->save();

        return $valor;
    }
}
