<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Perfil;

class OrdemServicoControllerTest extends TestCase
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

    public function test_store_creates_os_with_items_and_calculates_total()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Cliente A',
            'telefone' => '11999999999',
            'email' => null,
            'observacoes' => null,
            'status' => 'ativo',
        ]);

        $produto1 = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Produto 1',
            'tipo_medida' => 'unitario',
            'forma_calculo' => 'unitario',
            'custo_base' => 1.00,
            'preco_base' => 10.00,
            'preco' => 10.00,
            'status' => 'ativo',
        ]);

        $produto2 = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Produto 2',
            'tipo_medida' => 'unitario',
            'forma_calculo' => 'unitario',
            'custo_base' => 1.00,
            'preco_base' => 5.50,
            'preco' => 5.50,
            'status' => 'ativo',
        ]);

        $payload = [
            'cliente_id' => $cliente->id,
            'observacoes' => 'Obs',
            'itens' => [
                ['produto_id' => $produto1->id, 'quantidade' => 2],
                ['produto_id' => $produto2->id, 'quantidade' => 3],
            ],
        ];

        $res = $this->postJson('api/v1/ordens-servico', $payload);
        $res->assertStatus(201);

        $data = $res->json('data');
        $this->assertNotNull($data);

        $this->assertEquals('aberta', $data['status']);
        $this->assertCount(2, $data['itens']);

        // total = 2*10 + 3*5.5 = 36.5
        $this->assertEquals(36.5, (float) $data['valor_total']);

        $this->assertDatabaseHas('ordens_servico', [
            'empresa_id' => $user->empresa_id,
            'cliente_id' => $cliente->id,
        ]);

        $this->assertDatabaseHas('os_itens', [
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produto1->id,
            'valor_unitario' => 10.00,
            'valor_total' => 20.00,
            'status' => 'ativo',
        ]);

        $this->assertDatabaseHas('os_itens', [
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produto2->id,
            'valor_unitario' => 5.50,
            'valor_total' => 16.50,
            'status' => 'ativo',
        ]);
    }

    public function test_index_scopes_by_empresa()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Cliente A',
            'telefone' => '11999999999',
            'status' => 'ativo',
        ]);

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Produto 1',
            'tipo_medida' => 'unitario',
            'forma_calculo' => 'unitario',
            'custo_base' => 1.00,
            'preco_base' => 10.00,
            'preco' => 10.00,
            'status' => 'ativo',
        ]);

        $this->postJson('api/v1/ordens-servico', [
            'cliente_id' => $cliente->id,
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => 1],
            ],
        ])->assertStatus(201);

        $otherEmpresa = Empresa::create(['nome' => 'Outra', 'cnpj' => null, 'status' => true]);
        $otherCliente = Cliente::create(['empresa_id' => $otherEmpresa->id, 'nome' => 'C2', 'telefone' => '11999999998', 'status' => 'ativo']);
        $otherProduto = Produto::create(['empresa_id' => $otherEmpresa->id, 'nome' => 'P2', 'tipo_medida' => 'unitario', 'forma_calculo' => 'unitario', 'custo_base' => 1.00, 'preco_base' => 1.00, 'preco' => 1, 'status' => 'ativo']);

        $otherPerfilAdmin = Perfil::create([
            'empresa_id' => $otherEmpresa->id,
            'nome' => 'admin',
        ]);

        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => 'password',
            'empresa_id' => $otherEmpresa->id,
            'perfil_id' => $otherPerfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($otherUser, 'sanctum');

        $this->postJson('api/v1/ordens-servico', [
            'cliente_id' => $otherCliente->id,
            'itens' => [
                ['produto_id' => $otherProduto->id, 'quantidade' => 1],
            ],
        ])->assertStatus(201);

        $this->actingAs($user, 'sanctum');
        $res = $this->getJson('api/v1/ordens-servico');
        $res->assertStatus(200);
        $res->assertJsonCount(1, 'data');
    }

    public function test_store_calculates_total_for_metro_linear_using_comprimento()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Cliente A',
            'telefone' => '11999999999',
            'email' => null,
            'observacoes' => null,
            'status' => 'ativo',
        ]);

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Corte Linear',
            'tipo_medida' => 'metro_linear',
            'forma_calculo' => 'metro_linear',
            'custo_base' => 6.0000,
            'preco_base' => 10.0000,
            'preco' => 10.00,
            'status' => 'ativo',
        ]);

        $payload = [
            'cliente_id' => $cliente->id,
            'observacoes' => 'Obs',
            'itens' => [
                ['produto_id' => $produto->id, 'comprimento' => 2.5],
            ],
        ];

        $res = $this->postJson('api/v1/ordens-servico', $payload);
        $res->assertStatus(201);

        // total = 2.5 * 10 = 25
        $this->assertEquals(25.0, (float) $res->json('data.valor_total'));

        $this->assertDatabaseHas('os_itens', [
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produto->id,
            'quantidade' => 2.5,
            'comprimento' => 2.5,
            'valor_unitario' => 10.00,
            'valor_total' => 25.00,
            'status' => 'ativo',
        ]);
    }

    public function test_store_calculates_total_for_metro_quadrado_using_largura_x_altura()
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Cliente A',
            'telefone' => '11999999999',
            'email' => null,
            'observacoes' => null,
            'status' => 'ativo',
        ]);

        $produto = Produto::create([
            'empresa_id' => $user->empresa_id,
            'nome' => 'Chapa',
            'tipo_medida' => 'metro_quadrado',
            'forma_calculo' => 'metro_quadrado',
            'custo_base' => 8.0000,
            'preco_base' => 20.0000,
            'preco' => 20.00,
            'status' => 'ativo',
        ]);

        $payload = [
            'cliente_id' => $cliente->id,
            'observacoes' => 'Obs',
            'itens' => [
                ['produto_id' => $produto->id, 'largura' => 2, 'altura' => 3],
            ],
        ];

        $res = $this->postJson('api/v1/ordens-servico', $payload);
        $res->assertStatus(201);

        // fator = 2*3 = 6; total = 6 * 20 = 120
        $this->assertEquals(120.0, (float) $res->json('data.valor_total'));

        $this->assertDatabaseHas('os_itens', [
            'empresa_id' => $user->empresa_id,
            'produto_id' => $produto->id,
            'quantidade' => 6,
            'largura' => 2,
            'altura' => 3,
            'valor_unitario' => 20.00,
            'valor_total' => 120.00,
            'status' => 'ativo',
        ]);
    }
}
