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
        app(ProdutoVivoCalculoService::class)->recalcular($produto);

        return (float) ($produto->custo_total ?? $produto->custo_calculado ?? 0);
    }
}
