<?php

namespace App\Events;

class FinanceiroGeradoEvent
{
    public function __construct(
        public int $empresaId,
        public int $financeiroLancamentoId,
        public ?int $ordemServicoId,
        public ?int $clienteId,
        public int $usuarioId = 0,
    ) {
    }
}
