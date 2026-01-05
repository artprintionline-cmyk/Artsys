<?php

namespace Tests\Feature\Api\V1;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsumoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cria_insumo_e_lista_apenas_da_propria_empresa(): void
    {
        $empresa1 = Empresa::create(['nome' => 'Empresa 1', 'status' => true]);
        $perfilAdmin1 = Perfil::create(['empresa_id' => $empresa1->id, 'nome' => 'admin']);
        $user1 = User::create([
            'name' => 'Admin 1',
            'email' => 'admin1@test.com',
            'password' => 'password',
            'empresa_id' => $empresa1->id,
            'perfil_id' => $perfilAdmin1->id,
            'status' => true,
        ]);

        $empresa2 = Empresa::create(['nome' => 'Empresa 2', 'status' => true]);
        $perfilAdmin2 = Perfil::create(['empresa_id' => $empresa2->id, 'nome' => 'admin']);
        $user2 = User::create([
            'name' => 'Admin 2',
            'email' => 'admin2@test.com',
            'password' => 'password',
            'empresa_id' => $empresa2->id,
            'perfil_id' => $perfilAdmin2->id,
            'status' => true,
        ]);

        // Cria na empresa 1
        $this->actingAs($user1, 'sanctum');
        $res1 = $this->postJson('api/v1/insumos', [
            'nome' => 'Tinta',
            'sku' => 'INS-001',
            'custo_unitario' => 12.5,
            'unidade_medida' => 'un',
            'controla_estoque' => true,
            'ativo' => true,
        ]);
        $res1->assertStatus(201);
        $id1 = (int) $res1->json('data.id');

        // Cria na empresa 2
        $this->actingAs($user2, 'sanctum');
        $res2 = $this->postJson('api/v1/insumos', [
            'nome' => 'Solvente',
            'sku' => 'INS-002',
            'custo_unitario' => 3.2,
            'unidade_medida' => 'ml',
            'controla_estoque' => true,
            'ativo' => true,
        ]);
        $res2->assertStatus(201);
        $id2 = (int) $res2->json('data.id');

        // Lista como empresa 1: deve ver apenas o seu
        $this->actingAs($user1, 'sanctum');
        $list = $this->getJson('api/v1/insumos');
        $list->assertStatus(200);
        $list->assertJsonFragment(['id' => $id1]);
        $list->assertJsonMissing(['id' => $id2]);
    }

    public function test_material_v11_calcula_custo_unitario_a_partir_da_embalagem(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa 1', 'status' => true]);
        $perfilAdmin = Perfil::create(['empresa_id' => $empresa->id, 'nome' => 'admin']);
        $user = User::create([
            'name' => 'Admin 1',
            'email' => 'admin1@test.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');
        $res = $this->postJson('api/v1/insumos', [
            'nome' => 'Papel A4',
            'unidade_consumo' => 'folha',
            'tipo_embalagem' => 'Pacote',
            'valor_embalagem' => 28.90,
            'quantidade_por_embalagem' => 500,
            'ativo' => true,
            // mesmo se vier, deve ser ignorado no modo v1.1
            'custo_unitario' => 999,
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.unidade_medida', 'folha');
        $res->assertJsonPath('data.custo_unitario', '0.0578');
        $res->assertJsonPath('data.controla_estoque', false);
    }
}
