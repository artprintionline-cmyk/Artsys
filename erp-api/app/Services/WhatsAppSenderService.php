<?php

namespace App\Services;

use App\Models\WhatsAppMensagem;
use Carbon\Carbon;

class WhatsAppSenderService
{
    /**
     * Simula o envio de uma mensagem WhatsApp e registra no banco.
     *
     * @param int $empresaId
     * @param int|null $clienteId
     * @param string $numero
     * @param string $mensagem
     * @param string $contexto
     * @param int|null $referenciaId
     * @return bool
     */
    public function sendMessage(int $empresaId, ?int $clienteId, string $numero, string $mensagem, string $contexto, ?int $referenciaId = null): bool
    {
        // Simulação: sempre sucesso
        WhatsAppMensagem::create([
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'numero' => $numero,
            'mensagem' => $mensagem,
            'status' => 'enviado',
            'contexto' => $contexto,
            'referencia_id' => $referenciaId,
        ] + [ 'created_at' => Carbon::now() ]);

        return true;
    }
}
