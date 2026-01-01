<?php

namespace App\Services;

use App\Models\OrdemServico;

class OrdemServicoValorService
{
    /**
     * Recalcula o valor total da ordem somando os `valor_total`
     * dos itens ativos e persiste no registro da ordem.
     *
     * @param  OrdemServico  $ordemServico
     * @return float
     */
    public function recalcularValor(OrdemServico $ordemServico): float
    {
        $total = (float) $ordemServico
            ->itens()
            ->where('status', 'ativo')
            ->sum('valor_total');

        $ordemServico->valor_total = $total;
        $ordemServico->save();

        return $total;
    }
}
