<?php

namespace App\Jobs;

use App\Services\WhatsAppSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $empresaId;
    public ?int $clienteId;
    public ?int $ordemServicoId;
    public string $numero;
    public string $mensagem;
    public string $contexto;
    public ?int $referenciaId;
    public string $tipo;

    public function __construct(
        int $empresaId,
        ?int $clienteId,
        string $numero,
        string $mensagem,
        string $contexto,
        ?int $referenciaId = null,
        ?int $ordemServicoId = null,
        string $tipo = 'text'
    )
    {
        $this->empresaId = $empresaId;
        $this->clienteId = $clienteId;
        $this->numero = $numero;
        $this->mensagem = $mensagem;
        $this->contexto = $contexto;
        $this->referenciaId = $referenciaId;
        $this->ordemServicoId = $ordemServicoId;
        $this->tipo = $tipo;

        $this->onQueue('whatsapp');
    }

    public function handle(WhatsAppSenderService $sender): void
    {
        $sender->sendTextMessage(
            $this->empresaId,
            $this->clienteId,
            $this->numero,
            $this->mensagem,
            $this->contexto,
            $this->referenciaId,
            $this->ordemServicoId,
            $this->tipo
        );
    }
}
