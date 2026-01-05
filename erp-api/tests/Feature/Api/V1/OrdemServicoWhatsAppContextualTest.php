<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\EstoqueProduto;
use App\Models\WhatsAppMensagem;

class OrdemServicoWhatsAppContextualTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_historico_por_os_retorna_somente_mensagens_da_os(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Teste',
            'email' => 'user@test.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente A',
            'telefone' => '5599999999999',
            'status' => 'ativo',
        ]);

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Produto X',
            'sku' => null,
            'preco' => 100.00,
            'forma_calculo' => 'unitario',
            'custo_base' => 50.00,
            'preco_base' => 100.00,
            'tipo_medida' => 'unitario',
            'preco_manual' => null,
            'markup' => null,
            'custo_calculado' => 0,
            'preco_final' => 100.00,
            'status' => 'ativo',
        ]);

        EstoqueProduto::create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'quantidade_atual' => 10,
            'estoque_minimo' => 0,
        ]);

        $resCreate = $this->postJson('api/v1/ordens-servico', [
            'cliente_id' => $cliente->id,
            'data_entrega' => now()->addDays(7)->toDateString(),
            'descricao' => 'Pedido teste',
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => 1],
            ],
        ]);
        $resCreate->assertStatus(201);
        $osId = (int) $resCreate->json('data.id');

        WhatsAppMensagem::create([
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => $osId,
            'cliente_id' => $cliente->id,
            'numero' => $cliente->telefone,
            'mensagem' => 'Oi',
            'direcao' => 'entrada',
            'tipo' => 'text',
            'provider_message_id' => null,
            'status' => 'recebido',
            'contexto' => 'os',
            'referencia_id' => $osId,
            'payload' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WhatsAppMensagem::create([
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => null,
            'cliente_id' => $cliente->id,
            'numero' => $cliente->telefone,
            'mensagem' => 'Outra conversa',
            'direcao' => 'entrada',
            'tipo' => 'text',
            'provider_message_id' => null,
            'status' => 'recebido',
            'contexto' => 'inbound',
            'referencia_id' => null,
            'payload' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getJson("api/v1/ordens-servico/{$osId}/whatsapp");
        $res->assertStatus(200);
        $res->assertJsonPath('data.os.id', $osId);
        $this->assertCount(1, (array) $res->json('data.mensagens'));
    }

    public function test_post_enviar_texto_enfileira_job(): void
    {
        Queue::fake();

        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Teste',
            'email' => 'user@test.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente A',
            'telefone' => '5599999999999',
            'status' => 'ativo',
        ]);

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Produto X',
            'sku' => null,
            'preco' => 100.00,
            'forma_calculo' => 'unitario',
            'custo_base' => 50.00,
            'preco_base' => 100.00,
            'tipo_medida' => 'unitario',
            'preco_manual' => null,
            'markup' => null,
            'custo_calculado' => 0,
            'preco_final' => 100.00,
            'status' => 'ativo',
        ]);

        EstoqueProduto::create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'quantidade_atual' => 10,
            'estoque_minimo' => 0,
        ]);

        $resCreate = $this->postJson('api/v1/ordens-servico', [
            'cliente_id' => $cliente->id,
            'data_entrega' => now()->addDays(7)->toDateString(),
            'descricao' => 'Pedido teste',
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => 1],
            ],
        ]);
        $resCreate->assertStatus(201);
        $osId = (int) $resCreate->json('data.id');

        $res = $this->postJson("api/v1/ordens-servico/{$osId}/whatsapp/enviar", [
            'tipo' => 'texto',
            'mensagem' => 'Olá',
        ]);

        $res->assertStatus(202);

        Queue::assertPushed(SendWhatsAppMessageJob::class, function (SendWhatsAppMessageJob $job) use ($empresa, $cliente, $osId) {
            return $job->empresaId === (int) $empresa->id
                && $job->clienteId === (int) $cliente->id
                && $job->ordemServicoId === (int) $osId
                && $job->contexto === 'os';
        });
    }
}
