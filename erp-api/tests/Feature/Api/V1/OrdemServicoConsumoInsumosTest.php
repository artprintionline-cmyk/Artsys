<?php

namespace Tests\Feature\Api\V1;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EstoqueInsumo;
use App\Models\EstoqueInsumoMovimentacao;
use App\Models\EstoqueProduto;
use App\Models\Insumo;
use App\Models\Perfil;
use App\Models\Produto;
use App\Models\ProdutoInsumo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdemServicoConsumoInsumosTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalizar_os_consume_estoque_de_insumos(): void
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

        // Estoque do produto (não é consumido diretamente, mas mantém padrão dos testes existentes)
        EstoqueProduto::create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'quantidade_atual' => 10,
            'estoque_minimo' => 0,
        ]);

        $insumo = Insumo::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Tinta',
            'sku' => 'INS-001',
            'custo_unitario' => 10.00,
            'unidade_medida' => 'un',
            'controla_estoque' => true,
            'ativo' => true,
        ]);

        ProdutoInsumo::create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'insumo_id' => $insumo->id,
            'quantidade_base' => 1.5,
        ]);

        EstoqueInsumo::create([
            'empresa_id' => $empresa->id,
            'insumo_id' => $insumo->id,
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
        $osId = (int) $resCreate->json('data.id');

        // aberta -> em_producao -> aguardando_pagamento -> finalizada
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'em_producao'])->assertStatus(200);
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'aguardando_pagamento'])->assertStatus(200);
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'finalizada'])->assertStatus(200);

        $estoque = EstoqueInsumo::where('empresa_id', $empresa->id)->where('insumo_id', $insumo->id)->first();
        $this->assertNotNull($estoque);

        // consumo esperado: quantidade_base (1.5) * fator (quantidade do item: 2) = 3
        $this->assertEquals(7.0, round((float) $estoque->quantidade_atual, 4));

        $mov = EstoqueInsumoMovimentacao::where('empresa_id', $empresa->id)
            ->where('insumo_id', $insumo->id)
            ->where('origem', 'os')
            ->where('origem_id', $osId)
            ->where('tipo', 'saida')
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(3.0, round((float) $mov->quantidade, 4));
    }

    public function test_finalizar_os_bloqueia_quando_estoque_insumo_insuficiente(): void
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

        $insumo = Insumo::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Tinta',
            'sku' => 'INS-001',
            'custo_unitario' => 10.00,
            'unidade_medida' => 'un',
            'controla_estoque' => true,
            'ativo' => true,
        ]);

        ProdutoInsumo::create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'insumo_id' => $insumo->id,
            'quantidade_base' => 1.5,
        ]);

        // insuficiente para 1.5 * 2 = 3
        EstoqueInsumo::create([
            'empresa_id' => $empresa->id,
            'insumo_id' => $insumo->id,
            'quantidade_atual' => 1,
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
        $osId = (int) $resCreate->json('data.id');

        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'em_producao'])->assertStatus(200);
        $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'aguardando_pagamento'])->assertStatus(200);

        $resFinal = $this->putJson("api/v1/ordens-servico/{$osId}/status", ['status_destino' => 'finalizada']);
        $resFinal->assertStatus(422);
        $resFinal->assertJsonFragment(['message' => 'Estoque insuficiente para finalizar a OS.']);

        // garante que não consumiu nada
        $estoque = EstoqueInsumo::where('empresa_id', $empresa->id)->where('insumo_id', $insumo->id)->first();
        $this->assertNotNull($estoque);
        $this->assertEquals(1.0, round((float) $estoque->quantidade_atual, 4));

        $this->assertDatabaseHas('ordens_servico', [
            'id' => $osId,
            'empresa_id' => $empresa->id,
            'status_atual' => 'aguardando_pagamento',
        ]);
    }
}
