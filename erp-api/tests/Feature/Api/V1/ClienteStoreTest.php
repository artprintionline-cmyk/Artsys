<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use App\Models\Empresa;
use App\Models\User;

class ClienteStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_cliente_and_empresa_id_is_assigned()
    {
        $empresa = Empresa::create([
            'nome' => 'Empresa Teste',
            'cnpj' => '00000000000191',
            'status' => 'ativo',
        ]);

        $user = User::create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'status' => 'ativo',
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $payload = [
            'nome' => 'ACME Ltda',
            'telefone' => '(11)99999-0000',
            'email' => 'cliente@acme.test',
        ];

        $response = $this->postJson('/api/v1/clientes', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'empresa_id', 'nome', 'telefone', 'email', 'observacoes', 'status', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('clientes', [
            'nome' => 'ACME Ltda',
            'telefone' => '(11)99999-0000',
            'email' => 'cliente@acme.test',
            'empresa_id' => $empresa->id,
        ]);
    }
}
