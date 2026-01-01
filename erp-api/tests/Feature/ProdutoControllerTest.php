<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Produto;
use App\Models\Componente;
use App\Models\ProdutoComponente;

class ProdutoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithEmpresa(): User
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'cnpj' => null, 'status' => true]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
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
        $res->assertJsonCount(2);
    }

    public function test_store_creates_product_with_defaults()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'nome' => 'Produto X',
            'tipo_medida' => 'unitario',
            'descricao' => 'Desc',
        ];

        $res = $this->postJson('api/v1/produtos', $payload);
        $res->assertStatus(201);
        $res->assertJsonFragment(['nome' => 'Produto X', 'status' => 'ativo']);

        $this->assertDatabaseHas('produtos', ['empresa_id' => $user->empresa_id, 'nome' => 'Produto X']);
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
        $res->assertJsonStructure(['produto_componentes']);
    }

    public function test_update_modifies_product_without_recalculating()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'Old', 'tipo_medida' => 'unitario']);

        $res = $this->putJson("api/v1/produtos/{$produto->id}", ['nome' => 'New']);
        $res->assertStatus(200);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'nome' => 'New']);
    }

    public function test_destroy_marks_inactive()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'ToDel', 'tipo_medida' => 'unitario']);
        $res = $this->deleteJson("api/v1/produtos/{$produto->id}");
        $res->assertStatus(200);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'status' => 'inativo']);
    }

    public function test_adicionar_and_remover_componente_and_recalculate()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $produto = Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'PC', 'tipo_medida' => 'unitario', 'markup' => 2]);
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
        $res2->assertJsonStructure(['custo_calculado','preco_final']);

        // remover componente
        $pc = ProdutoComponente::where('produto_id', $produto->id)->first();
        $res3 = $this->deleteJson("api/v1/produtos/{$produto->id}/componentes/{$pc->componente_id}");
        $res3->assertStatus(200);
        $this->assertDatabaseHas('produto_componentes', ['id' => $pc->id, 'status' => 'inativo']);
    }
}
