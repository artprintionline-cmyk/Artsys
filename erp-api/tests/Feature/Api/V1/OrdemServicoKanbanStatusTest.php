<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\EstoqueProduto;

class OrdemServicoKanbanStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_status_destino_valida_transicoes_e_registra_historico(): void
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
            'telefone' => '000000000',
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

        $payloadOs = [
            'cliente_id' => $cliente->id,
            'data_entrega' => now()->addDays(7)->toDateString(),
            'descricao' => 'Pedido teste',
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                ],
            ],
        ];

        $resCreate = $this->postJson('api/v1/ordens-servico', $payloadOs);
        $resCreate->assertStatus(201);

        $osId = $resCreate->json('data.id');

        // aberta -> em_producao
        $res1 = $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'em_producao']);
        $res1->assertStatus(200);
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $osId,
            'empresa_id' => $empresa->id,
            'status_atual' => 'em_producao',
        ]);
        $this->assertDatabaseHas('os_historico', [
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => $osId,
            'status_novo' => 'em_producao',
        ]);

        // em_producao -> finalizada (inválido)
        $resInvalid = $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'finalizada']);
        $resInvalid->assertStatus(422);

        // em_producao -> aguardando_pagamento
        $res2 = $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'aguardando_pagamento']);
        $res2->assertStatus(200);
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $osId,
            'empresa_id' => $empresa->id,
            'status_atual' => 'aguardando_pagamento',
        ]);

        // aguardando_pagamento -> finalizada
        $res3 = $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'finalizada']);
        $res3->assertStatus(200);
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $osId,
            'empresa_id' => $empresa->id,
            'status_atual' => 'finalizada',
        ]);
    }

    public function test_put_status_destino_nao_finaliza_sem_itens_ativos(): void
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
            'telefone' => '000000000',
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

        $payloadOs = [
            'cliente_id' => $cliente->id,
            'data_entrega' => now()->addDays(7)->toDateString(),
            'descricao' => 'Pedido teste',
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                ],
            ],
        ];

        $resCreate = $this->postJson('api/v1/ordens-servico', $payloadOs);
        $resCreate->assertStatus(201);

        $osId = $resCreate->json('data.id');
        $itemInicialId = $resCreate->json('data.itens.0.id');

        // Remove o único item, ficando sem itens ativos
        $resRemove = $this->deleteJson("api/v1/ordens-servico/{$osId}/itens/{$itemInicialId}");
        $resRemove->assertStatus(200);

        // aberta -> em_producao -> aguardando_pagamento
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'em_producao'])->assertStatus(200);
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'aguardando_pagamento'])->assertStatus(200);

        // aguardando_pagamento -> finalizada (inválido por não ter itens)
        $resFinaliza = $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'finalizada']);
        $resFinaliza->assertStatus(422);
        $resFinaliza->assertJsonFragment(['message' => 'A OS precisa ter ao menos 1 item para ser finalizada.']);

        $this->assertDatabaseHas('ordens_servico', [
            'id' => $osId,
            'empresa_id' => $empresa->id,
            'status_atual' => 'aguardando_pagamento',
        ]);
    }

    public function test_nao_permite_editar_os_finalizada(): void
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
            'telefone' => '000000000',
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

        $payloadOs = [
            'cliente_id' => $cliente->id,
            'data_entrega' => now()->addDays(7)->toDateString(),
            'descricao' => 'Pedido teste',
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                ],
            ],
        ];

        $resCreate = $this->postJson('api/v1/ordens-servico', $payloadOs);
        $resCreate->assertStatus(201);

        $osId = $resCreate->json('data.id');

        // aberta -> em_producao -> aguardando_pagamento -> finalizada
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'em_producao'])->assertStatus(200);
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'aguardando_pagamento'])->assertStatus(200);
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'finalizada'])->assertStatus(200);

        // Tenta alterar observações (deve bloquear)
        $resUpdate = $this->putJson("api/v1/ordens-servico/{$osId}", ['observacoes' => 'teste']);
        $resUpdate->assertStatus(422);

        // Tenta adicionar item (deve bloquear)
        $resAddItem = $this->postJson("api/v1/ordens-servico/{$osId}/itens", [
            'produto_id' => $produto->id,
            'quantidade' => 1,
        ]);
        $resAddItem->assertStatus(422);
    }
}
