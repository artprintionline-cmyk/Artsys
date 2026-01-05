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

        $totalOs = round((float) ($ordemServico->valor_total ?? 0.0), 2);
        if ($totalOs <= 0) {
            throw new \InvalidArgumentException('Ordem de serviço sem valor total válido.');
        }

        $totalParcelas = 0.0;
        foreach ($parcelas as $index => $parcela) {
            if (!isset($parcela['valor']) || !isset($parcela['vencimento'])) {
                throw new \InvalidArgumentException("Parcela na posição {$index} inválida. Esperado keys 'valor' e 'vencimento'.");
            }

            $valor = (float) $parcela['valor'];
            if ($valor <= 0) {
                throw new \InvalidArgumentException("Valor da parcela na posição {$index} inválido.");
            }
            $totalParcelas += $valor;

            try {
                Carbon::parse($parcela['vencimento'])->toDateString();
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException("Vencimento da parcela na posição {$index} inválido.");
            }
        }

        $totalParcelas = round($totalParcelas, 2);
        if (abs($totalParcelas - $totalOs) > 0.01) {
            throw new \InvalidArgumentException('A soma das parcelas deve ser igual ao valor total da OS.');
        }

        DB::transaction(function () use ($ordemServico, $parcelas) {
            $jaExiste = ContaReceber::where('empresa_id', $ordemServico->empresa_id)
                ->where('ordem_servico_id', $ordemServico->id)
                ->exists();

            if ($jaExiste) {
                throw new \InvalidArgumentException('Parcelas já foram geradas para esta OS.');
            }

            foreach ($parcelas as $index => $parcela) {
                if (!isset($parcela['valor']) || !isset($parcela['vencimento'])) {
                    throw new \InvalidArgumentException("Parcela na posição {$index} inválida. Esperado keys 'valor' e 'vencimento'.");
                }

                $valor = $parcela['valor'];
                $vencimento = $parcela['vencimento'];

                $dataVencimento = Carbon::parse($vencimento)->toDateString();

                $dup = ContaReceber::where('empresa_id', $ordemServico->empresa_id)
                    ->where('ordem_servico_id', $ordemServico->id)
                    ->where('vencimento', $dataVencimento)
                    ->exists();
                if ($dup) {
                    throw new \InvalidArgumentException('Já existe parcela para este vencimento.');
                }

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
