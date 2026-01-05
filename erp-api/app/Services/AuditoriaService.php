<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use Illuminate\Http\Request;

class AuditoriaService
{
    /**
     * Registra um log de auditoria (best-effort).
     * Nunca deve quebrar o fluxo principal.
     */
    public function log(
        Request $request,
        string $acao,
        string $entidade,
        ?int $entidadeId = null,
        ?array $dadosAnteriores = null,
        ?array $dadosNovos = null
    ): void {
        try {
            $empresaId = $request->attributes->get('empresa_id');
            if (! $empresaId) {
                return;
            }

            $user = $request->user();

            AuditoriaLog::create([
                'empresa_id' => (int) $empresaId,
                'user_id' => $user ? (int) $user->id : null,
                'acao' => $acao,
                'entidade' => $entidade,
                'entidade_id' => $entidadeId,
                'dados_anteriores' => $dadosAnteriores,
                'dados_novos' => $dadosNovos,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // não bloquear
        }
    }
}
