<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\Cliente;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMensagem;
use App\Services\WhatsAppTemplateService;
use App\Services\WhatsAppSenderService;

class WhatsAppFluxoCompletoTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_template_render_e_envio_registra_mensagem(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa WA', 'status' => true]);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente WA',
            'telefone' => '999999999',
            'status' => 'ativo',
        ]);

        // Criar template
        WhatsAppTemplate::create([
            'empresa_id' => $empresa->id,
            'chave' => 'os_criada',
            'conteudo' => 'Olá {{cliente}}, sua OS {{os}} no valor de {{valor}} foi criada.',
            'ativo' => true,
        ]);

        $templateService = new WhatsAppTemplateService();
        $senderService = new WhatsAppSenderService();

        $mensagem = $templateService->renderTemplate($empresa->id, 'os_criada', [
            'cliente' => $cliente->nome,
            'valor' => 'R$ 100,00',
            'os' => 'OS-0001',
        ]);

        $this->assertNotEmpty($mensagem);

        $sent = $senderService->sendMessage(
            $empresa->id,
            $cliente->id,
            $cliente->telefone,
            $mensagem,
            'os',
            1
        );

        $this->assertTrue($sent);

        $this->assertDatabaseHas('whatsapp_mensagens', [
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => $cliente->telefone,
            'mensagem' => $mensagem,
            'status' => 'enviado',
            'contexto' => 'os',
            'referencia_id' => 1,
        ]);

        $this->assertDatabaseCount('whatsapp_mensagens', 1);
    }
}
