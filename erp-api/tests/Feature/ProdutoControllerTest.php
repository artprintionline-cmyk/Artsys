<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Produto;
use App\Models\Componente;
use App\Models\ProdutoComponente;
use App\Models\CompraItem;


class ProdutoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithEmpresa(): User
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'cnpj' => null, 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        return $user;
    }

    public function test_index_lists_only_empresa_products()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        // other empresa
        $otherEmpresa = Empresa::create(['nome' => 'Outra', 'status' => true]);
        Produto::create(['empresa_id' => $otherEmpresa->id, 'nome' => 'Outro Produto', 'tipo_medida' => 'unitario']);

        // our empresa
        Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'Prod A', 'tipo_medida' => 'unitario']);
        Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'Prod B', 'tipo_medida' => 'unitario']);

        $res = $this->getJson('api/v1/produtos');
        $res->assertStatus(200);
        $res->assertJsonCount(2, 'data');
    }

    public function test_store_creates_product_with_defaults()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'nome' => 'Produto X',
            'custo_base' => 5,
            'forma_calculo' => 'unitario',
            'preco_base' => 10.5,
            'descricao' => 'Desc',
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(201);
        $res->assertJsonFragment(['nome' => 'Produto X']);

        $this->assertDatabaseHas('produtos', ['empresa_id' => $user->empresa_id, 'nome' => 'Produto X']);
    }

    public function test_store_generates_sku_automatically_and_ignores_client_input()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'nome' => 'Produto SKU',
            'sku' => 'HACK123',
            'custo_base' => 5,
            'forma_calculo' => 'unitario',
            'preco_base' => 10,
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(201);

        $sku = $res->json('data.sku');
        $this->assertNotEmpty($sku);
        $this->assertNotEquals('HACK123', $sku);
        $this->assertMatchesRegularExpression('/^ART\d{6,8}$/', $sku);

        $this->assertDatabaseHas('produtos', [
            'empresa_id' => $user->empresa_id,
            'nome' => 'Produto SKU',
            'sku' => $sku,
        ]);
    }

    public function test_store_generates_unique_sku_per_empresa()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'custo_base' => 1,
            'forma_calculo' => 'unitario',
            'preco_base' => 2,
        ];

        $r1 = $this->postJson('api/v1/produtos', array_merge($payload, ['nome' => 'P1']));
        $r1->assertStatus(201);
        $sku1 = $r1->json('data.sku');

        $r2 = $this->postJson('api/v1/produtos', array_merge($payload, ['nome' => 'P2']));
        $r2->assertStatus(201);
        $sku2 = $r2->json('data.sku');

        $this->assertNotEmpty($sku1);
        $this->assertNotEmpty($sku2);
        $this->assertNotEquals($sku1, $sku2);
    }

    public function test_store_calculates_cost_with_compras_itens()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $insumo = CompraItem::create([
            'empresa_id' => $user->empresa_id,
            'tipo' => 'insumo',
            'nome' => 'Tinta',
            'unidade_compra' => 'un',
            'preco_medio' => 2.0,
            'ativo' => true,
        ]);

        $equip = CompraItem::create([
            'empresa_id' => $user->empresa_id,
            'tipo' => 'equipamento',
            'nome' => 'Equip 1 (uso)',
            'unidade_compra' => 'uso',
            'preco_medio' => 3.0,
            'ativo' => true,
        ]);

        $payload = [
            'nome' => 'Produto com estimativa',
            'custo_base' => 1.0,
            'forma_calculo' => 'unitario',
            'preco_base' => 200.0,
            'compras_itens' => [
                ['compra_item_id' => $insumo->id, 'quantidade_base' => 10],
                ['compra_item_id' => $equip->id, 'quantidade_base' => 10],
            ],
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(201);

        // custo compras: 2*10 + 3*10 = 50
        // custo total = 1 + 50 = 51
        $res->assertJsonFragment(['custo_total' => '51.0000']);

        $produtoId = (int) $res->json('data.id');
        $this->assertDatabaseHas('produto_compras_itens', [
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produtoId,
            'compra_item_id' => $insumo->id,
        ]);
        $this->assertDatabaseHas('produto_compras_itens', [
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produtoId,
            'compra_item_id' => $equip->id,
        ]);
    }

    public function test_store_allows_when_custo_total_is_zero()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'nome' => 'Produto Sem Planejamento',
            'forma_calculo' => 'unitario',
            'preco_base' => 10,
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(201);
    }

    public function test_store_rejects_compra_item_from_other_empresa_and_rolls_back()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $otherEmpresa = Empresa::create(['nome' => 'Outra', 'status' => true]);
        $itemOther = CompraItem::create([
            'empresa_id' => $otherEmpresa->id,
            'tipo' => 'insumo',
            'nome' => 'Item Outra Empresa',
            'unidade_compra' => 'un',
            'preco_medio' => 10.0,
            'ativo' => true,
        ]);

        $payload = [
            'nome' => 'Produto Com Mat',
            'custo_base' => 1,
            'forma_calculo' => 'unitario',
            'preco_base' => 100,
            'compras_itens' => [
                ['compra_item_id' => $itemOther->id, 'quantidade_base' => 1],
            ],
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(404);

        $this->assertDatabaseMissing('produtos', [
            'empresa_id' => $user->empresa_id,
            'nome' => 'Produto Com Mat',
        ]);
    }

    public function test_store_allows_price_below_estimated_cost_when_compras_itens_present()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $item = CompraItem::create([
            'empresa_id' => $user->empresa_id,
            'tipo' => 'insumo',
            'nome' => 'Item 10',
            'unidade_compra' => 'un',
            'preco_medio' => 10.0,
            'ativo' => true,
        ]);

        $payload = [
            'nome' => 'Produto Barato',
            'forma_calculo' => 'unitario',
            'preco_base' => 5,
            'compras_itens' => [
                ['compra_item_id' => $item->id, 'quantidade_base' => 1],
            ],
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(201);
    }

    public function test_update_rejects_compra_item_from_other_empresa_and_keeps_previous_state()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'P1',
            'tipo_medida' => 'unitario',
            'custo_base' => 10,
            'forma_calculo' => 'unitario',
            'preco_base' => 50,
            'ativo' => true,
            'status' => 'ativo',
        ]);

        $otherEmpresa = Empresa::create(['nome' => 'Outra', 'status' => true]);
        $itemOther = CompraItem::create([
            'empresa_id' => $otherEmpresa->id,
            'tipo' => 'insumo',
            'nome' => 'Item Outra',
            'unidade_compra' => 'un',
            'preco_medio' => 10.0,
            'ativo' => true,
        ]);

        $res = $this->putJson("api/v1/produtos/{$produto->id}", [
            'compras_itens' => [
                ['compra_item_id' => $itemOther->id, 'quantidade_base' => 1],
            ],
        ]);

        $res->assertStatus(404);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'nome' => 'P1']);
        $this->assertDatabaseMissing('produto_compras_itens', ['empresa_id' => $user->empresa_id, 'produto_id' => $produto->id, 'compra_item_id' => $itemOther->id]);
    }

    public function test_show_returns_product_with_components()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'P', 'tipo_medida' => 'unitario']);
        $comp = Componente::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'C',
            'tipo' => 'matéria-prima',
            'unidade_base' => 'un',
            'status' => 'ativo'
        ]);

        ProdutoComponente::create([
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produto->id,
            'componente_id' => $comp->id,
            'quantidade' => 2,
            'custo_unitario' => 5,
            'custo_total' => 10,
            'status' => 'ativo',
        ]);

        $res = $this->getJson("api/v1/produtos/{$produto->id}");
        $res->assertStatus(200);
        $res->assertJsonFragment(['nome' => 'P']);
        $res->assertJsonStructure(['data' => ['produto_componentes']]);
    }

    public function test_update_modifies_product_without_recalculating()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Old',
            'tipo_medida' => 'unitario',
            'custo_base' => 10,
            'preco_venda' => 20,
            'custo_total' => 10,
            'lucro' => 10,
            'margem_percentual' => 100,
            'ativo' => true,
            'status' => 'ativo',
        ]);

        $res = $this->putJson("api/v1/produtos/{$produto->id}", ['nome' => 'New']);
        $res->assertStatus(200);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'nome' => 'New']);
    }

    public function test_destroy_marks_inactive()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'ToDel',
            'tipo_medida' => 'unitario',
            'custo_base' => 10,
            'preco_venda' => 20,
            'custo_total' => 10,
            'lucro' => 10,
            'margem_percentual' => 100,
            'ativo' => true,
            'status' => 'ativo',
        ]);
        $res = $this->deleteJson("api/v1/produtos/{$produto->id}");
        $res->assertStatus(200);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'status' => 'inativo']);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'ativo' => 0]);
    }

    public function test_adicionar_and_remover_componente_and_recalculate()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'PC',
            'tipo_medida' => 'unitario',
            'custo_base' => 10,
            'preco_venda' => 25,
            'custo_total' => 10,
            'lucro' => 15,
            'margem_percentual' => 150,
            'ativo' => true,
            'status' => 'ativo',
        ]);
        $comp = Componente::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Comp',
            'tipo' => 'matéria-prima',
            'unidade_base' => 'un',
            'status' => 'ativo'
        ]);

        // adicionar componente
        $payload = ['componente_id' => $comp->id, 'quantidade' => 3, 'custo_unitario' => 4];
        $res = $this->postJson("api/v1/produtos/{$produto->id}/componentes", $payload);
        $res->assertStatus(201);

        $this->assertDatabaseHas('produto_componentes', ['produto_id' => $produto->id, 'componente_id' => $comp->id, 'custo_total' => 12]);

        // recalcular custo explicitamente
        $res2 = $this->postJson("api/v1/produtos/{$produto->id}/recalcular-custo");
        $res2->assertStatus(200);
        $res2->assertJsonStructure(['custo_total','lucro','margem_percentual','custo_calculado','preco_venda']);

        // remover componente
        $pc = ProdutoComponente::where('produto_id', $produto->id)->first();
        $res3 = $this->deleteJson("api/v1/produtos/{$produto->id}/componentes/{$pc->componente_id}");
        $res3->assertStatus(200);
        $this->assertDatabaseHas('produto_componentes', ['id' => $pc->id, 'status' => 'inativo']);
    }
}
