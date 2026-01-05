<?php

namespace App\Services;

use App\Models\SaasAuditoria;

class SaasAuditoriaService
{
    /**
     * Best-effort: não deve quebrar fluxo.
     */
    public function log(string $acao, ?int $empresaId = null, ?int $userId = null, ?array $payload = null): void
    {
        try {
            SaasAuditoria::create([
                'empresa_id' => $empresaId,
                'user_id' => $userId,
                'acao' => $acao,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            // não bloquear
        }
    }
}
