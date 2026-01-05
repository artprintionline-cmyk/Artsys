<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Produto;
use App\Models\EstoqueProduto;

class EstoqueAjusteValidacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajuste_exige_motivo(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Estoque', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Estoque',
            'email' => 'estoque@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Produto X',
            'sku' => null,
            'preco' => 10.00,
            'tipo_medida' => 'unitario',
            'preco_manual' => 10.00,
            'markup' => null,
            'custo_calculado' => 0,
            'preco_final' => 10.00,
            'status' => 'ativo',
        ]);

        $res = $this->postJson('api/v1/estoque/ajuste', [
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 1,
        ]);

        $res->assertStatus(422);
    }

    public function test_ajuste_bloqueia_saida_com_estoque_insuficiente(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Estoque', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Estoque',
            'email' => 'estoque2@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Produto X',
            'sku' => null,
            'preco' => 10.00,
            'tipo_medida' => 'unitario',
            'preco_manual' => 10.00,
            'markup' => null,
            'custo_calculado' => 0,
            'preco_final' => 10.00,
            'status' => 'ativo',
        ]);

        EstoqueProduto::create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'quantidade_atual' => 1,
            'estoque_minimo' => 0,
        ]);

        $res = $this->postJson('api/v1/estoque/ajuste', [
            'produto_id' => $produto->id,
            'tipo' => 'saida',
            'quantidade' => 2,
            'motivo' => 'Ajuste teste',
        ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['message' => 'Estoque insuficiente.']);
    }
}
