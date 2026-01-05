<?php

namespace App\Events;

class OsCriadaEvent
{
    public function __construct(
        public int $empresaId,
        public int $ordemServicoId,
        public int $usuarioId = 0,
    ) {
    }
}
