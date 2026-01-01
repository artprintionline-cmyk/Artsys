<?php

namespace App\Services;

use App\Models\ContaReceber;
use App\Models\OrdemServico;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeradorContaReceberService
{
    /**
     * Gera parcelas (contas a receber) a partir de uma Ordem de Serviço.
     *
     * @param OrdemServico $ordemServico
     * @param array $parcelas Array of ['valor' => float, 'vencimento' => 'YYYY-MM-DD']
     * @return void
     * @throws \InvalidArgumentException
     */
    public function gerarParcelas(OrdemServico $ordemServico, array $parcelas): void
    {
        if (empty($parcelas)) {
            return;
        }

        DB::transaction(function () use ($ordemServico, $parcelas) {
            foreach ($parcelas as $index => $parcela) {
                if (!isset($parcela['valor']) || !isset($parcela['vencimento'])) {
                    throw new \InvalidArgumentException("Parcela na posição {$index} inválida. Esperado keys 'valor' e 'vencimento'.");
                }

                $valor = $parcela['valor'];
                $vencimento = $parcela['vencimento'];

                $dataVencimento = Carbon::parse($vencimento)->toDateString();

                ContaReceber::create([
                    'empresa_id' => $ordemServico->empresa_id,
                    'ordem_servico_id' => $ordemServico->id,
                    'cliente_id' => $ordemServico->cliente_id,
                    'valor' => $valor,
                    'vencimento' => $dataVencimento,
                    'status' => 'aberta',
                    'observacao' => null,
                ]);
            }
        });
    }
}
