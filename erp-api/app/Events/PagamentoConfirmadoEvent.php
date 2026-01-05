<?php

namespace App\Events;

class PagamentoConfirmadoEvent
{
    public function __construct(
        public int $empresaId,
        public int $financeiroLancamentoId,
        public ?int $ordemServicoId,
        public ?int $clienteId,
        public string $origem = 'sistema',
    ) {
    }
}
