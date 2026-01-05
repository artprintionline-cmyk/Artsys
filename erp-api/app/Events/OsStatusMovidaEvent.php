<?php

namespace App\Events;

class OsStatusMovidaEvent
{
    public function __construct(
        public int $empresaId,
        public int $ordemServicoId,
        public string $statusAnterior,
        public string $statusNovo,
        public int $usuarioId = 0,
    ) {
    }
}
