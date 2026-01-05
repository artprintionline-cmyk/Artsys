<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\OrdemServico;
use App\Models\OsItem;
use App\Models\OsHistorico;

class OrdemServicoFluxoCompletoTest extends TestCase
{
    use RefreshDatabase;

    public function test_fluxo_completo_da_ordem_de_servico(): void
    {
        // 1) Preparação: empresa, usuário, cliente e produto
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
            'preco_final' => 100.00, // preço definido conforme requisito
            'status' => 'ativo',
        ]);

        // 2) Criar Ordem de Serviço
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

        $this->assertDatabaseHas('ordens_servico', [
            'id' => $osId,
            'empresa_id' => $empresa->id,
            'status_atual' => 'aberta',
            'valor_total' => 200.00,
        ]);

        $ordem = OrdemServico::find($osId);
        $this->assertNotNull($ordem);

        // 3) Adicionar item à OS
        $payloadItem = [
            'produto_id' => $produto->id,
            'quantidade' => 1,
        ];

        $resItem = $this->postJson("api/v1/ordens-servico/{$osId}/itens", $payloadItem);
        $resItem->assertStatus(201);

        $itemId = $resItem->json('id');

        $this->assertDatabaseHas('os_itens', [
            'id' => $itemId,
            'ordem_servico_id' => $osId,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 100.00,
            'valor_total' => 100.00,
            'status' => 'ativo',
        ]);

        // 4) Recalcular valor da OS (automático após adicionar item)
        $ordem->refresh();
        $this->assertEquals(300.00, (float) $ordem->valor_total);

        // 5) Alterar status da OS
        $resStatus = $this->postJson("api/v1/ordens-servico/{$osId}/status", ['status' => 'producao']);
        $resStatus->assertStatus(200);

        $ordem->refresh();
        $this->assertEquals('em_producao', $ordem->status_atual);

        $this->assertDatabaseHas('os_historico', [
            'ordem_servico_id' => $osId,
            'status_novo' => 'em_producao',
        ]);

        // 6) Remover itens da OS
        $resRemoveAdicionado = $this->deleteJson("api/v1/ordens-servico/{$osId}/itens/{$itemId}");
        $resRemoveAdicionado->assertStatus(200);

        $resRemoveInicial = $this->deleteJson("api/v1/ordens-servico/{$osId}/itens/{$itemInicialId}");
        $resRemoveInicial->assertStatus(200);

        $this->assertDatabaseHas('os_itens', [
            'id' => $itemId,
            'status' => 'inativo',
        ]);

        $this->assertDatabaseHas('os_itens', [
            'id' => $itemInicialId,
            'status' => 'inativo',
        ]);

        $ordem->refresh();
        $this->assertEquals(0.00, (float) $ordem->valor_total);

        // 7) Cancelar OS
        $resCancel = $this->deleteJson("api/v1/ordens-servico/{$osId}");
        $resCancel->assertStatus(200);

        $ordem->refresh();
        $this->assertEquals('cancelada', $ordem->status_atual);

        $this->assertDatabaseHas('os_historico', [
            'ordem_servico_id' => $osId,
            'status_novo' => 'cancelada',
        ]);
    }
}
