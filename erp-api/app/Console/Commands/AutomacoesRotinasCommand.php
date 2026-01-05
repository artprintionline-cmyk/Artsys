<?php

namespace App\Console\Commands;

use App\Models\AutomacaoConfig;
use App\Models\FinanceiroLancamento;
use App\Services\AutomacoesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutomacoesRotinasCommand extends Command
{
    protected $signature = 'automacoes:rotinas {--empresa-id=}';

    protected $description = 'Executa rotinas de automações por tempo (financeiro pendente/vencido e OS parada).';

    public function handle(AutomacoesService $automacoes): int
    {
        $empresaIdFilter = $this->option('empresa-id');
        $empresaIdFilter = is_numeric($empresaIdFilter) ? (int) $empresaIdFilter : null;

        $configs = AutomacaoConfig::query()
            ->where('ativo', true)
            ->whereIn('evento', ['financeiro_pendente', 'financeiro_vencido', 'os_parada'])
            ->when($empresaIdFilter, fn ($q) => $q->where('empresa_id', $empresaIdFilter))
            ->get();

        $hoje = now()->toDateString();

        foreach ($configs as $cfg) {
            $empresaId = (int) $cfg->empresa_id;
            $param = is_array($cfg->parametros) ? $cfg->parametros : [];
            $dias = isset($param['dias']) ? (int) $param['dias'] : 0;
            if ($dias <= 0) {
                // parâmetros inválidos: ignora
                continue;
            }

            if ($cfg->evento === 'financeiro_pendente') {
                $targetDate = Carbon::parse($hoje)->addDays($dias)->toDateString();

                $lancs = FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pendente')
                    ->whereDate('data_vencimento', $targetDate)
                    ->get(['id', 'cliente_id', 'ordem_servico_id']);

                foreach ($lancs as $l) {
                    $automacoes->dispatchEvento(
                        $empresaId,
                        'financeiro_pendente',
                        'financeiro',
                        (int) $l->id,
                        [
                            'financeiro_lancamento_id' => (int) $l->id,
                            'cliente_id' => $l->cliente_id ? (int) $l->cliente_id : null,
                            'ordem_servico_id' => $l->ordem_servico_id ? (int) $l->ordem_servico_id : null,
                            'dias' => $dias,
                            'data_ref' => $targetDate,
                        ]
                    );
                }
            }

            if ($cfg->evento === 'financeiro_vencido') {
                $targetDate = Carbon::parse($hoje)->subDays($dias)->toDateString();

                $lancs = FinanceiroLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pendente')
                    ->whereDate('data_vencimento', $targetDate)
                    ->get(['id', 'cliente_id', 'ordem_servico_id']);

                foreach ($lancs as $l) {
                    $automacoes->dispatchEvento(
                        $empresaId,
                        'financeiro_vencido',
                        'financeiro',
                        (int) $l->id,
                        [
                            'financeiro_lancamento_id' => (int) $l->id,
                            'cliente_id' => $l->cliente_id ? (int) $l->cliente_id : null,
                            'ordem_servico_id' => $l->ordem_servico_id ? (int) $l->ordem_servico_id : null,
                            'dias' => $dias,
                            'data_ref' => $targetDate,
                        ]
                    );
                }
            }

            if ($cfg->evento === 'os_parada') {
                $cutoff = now()->subDays($dias);

                // last_move_at = max(os_historico.created_at) ou os.updated_at
                $rows = DB::table('ordens_servico as os')
                    ->leftJoin('os_historico as h', function ($join) use ($empresaId) {
                        $join->on('h.ordem_servico_id', '=', 'os.id')
                            ->where('h.empresa_id', '=', $empresaId);
                    })
                    ->selectRaw('os.id, os.status_atual, COALESCE(MAX(h.created_at), os.updated_at) as last_move_at')
                    ->where('os.empresa_id', $empresaId)
                    ->whereIn('os.status_atual', ['aberta', 'em_producao', 'aguardando_pagamento', 'criada', 'em_andamento', 'producao', 'pendente', 'pendencia', 'faturado'])
                        ->whereIn('os.status_atual', ['aberta', 'criada', 'em_producao', 'em_andamento', 'producao', 'aguardando_pagamento', 'faturado', 'pendencia', 'pendente'])
                    ->groupBy('os.id', 'os.status_atual', 'os.updated_at')
                    ->havingRaw('COALESCE(MAX(h.created_at), os.updated_at) <= ?', [$cutoff])
                    ->get();

                foreach ($rows as $r) {
                    $automacoes->dispatchEvento(
                        $empresaId,
                        'os_parada',
                        'os',
                        (int) $r->id,
                        [
                            'ordem_servico_id' => (int) $r->id,
                            'status_atual' => (string) $r->status_atual,
                            'dias' => $dias,
                            'data_ref' => $cutoff->toDateString(),
                        ]
                    );
                }
            }
        }

        $this->info('Rotinas de automações executadas.');
        return self::SUCCESS;
    }
}
