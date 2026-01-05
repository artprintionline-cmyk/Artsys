<?php

namespace App\Listeners;

use App\Events\FinanceiroGeradoEvent;
use App\Events\OsCriadaEvent;
use App\Events\OsStatusMovidaEvent;
use App\Events\PagamentoConfirmadoEvent;
use App\Services\AutomacoesService;

class DispatchAutomacoesFromEvent
{
    public function __construct(private AutomacoesService $automacoes)
    {
    }

    public function onOsCriada(OsCriadaEvent $event): void
    {
        $this->automacoes->dispatchEvento(
            $event->empresaId,
            'os_criada',
            'os',
            $event->ordemServicoId,
            [
                'ordem_servico_id' => $event->ordemServicoId,
            ]
        );
    }

    public function onOsStatusMovida(OsStatusMovidaEvent $event): void
    {
        $this->automacoes->dispatchEvento(
            $event->empresaId,
            'os_status_movida',
            'os',
            $event->ordemServicoId,
            [
                'ordem_servico_id' => $event->ordemServicoId,
                'status_anterior' => $event->statusAnterior,
                'status_novo' => $event->statusNovo,
            ]
        );
    }

    public function onFinanceiroGerado(FinanceiroGeradoEvent $event): void
    {
        $this->automacoes->dispatchEvento(
            $event->empresaId,
            'financeiro_gerado',
            'financeiro',
            $event->financeiroLancamentoId,
            [
                'financeiro_lancamento_id' => $event->financeiroLancamentoId,
                'ordem_servico_id' => $event->ordemServicoId,
                'cliente_id' => $event->clienteId,
            ]
        );
    }

    public function onPagamentoConfirmado(PagamentoConfirmadoEvent $event): void
    {
        $this->automacoes->dispatchEvento(
            $event->empresaId,
            'pagamento_confirmado',
            'financeiro',
            $event->financeiroLancamentoId,
            [
                'financeiro_lancamento_id' => $event->financeiroLancamentoId,
                'ordem_servico_id' => $event->ordemServicoId,
                'cliente_id' => $event->clienteId,
                'origem' => $event->origem,
            ]
        );
    }
}
