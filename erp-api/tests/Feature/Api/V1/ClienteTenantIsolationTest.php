<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Perfil;
use Illuminate\Support\Facades\Hash;

class ClienteTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_clients_from_their_own_empresa()
    {
        // Create two companies
        $empresaA = Empresa::create([
            'nome' => 'Empresa A',
            'cnpj' => '11111111000111',
            'status' => 'ativo',
        ]);

        $empresaB = Empresa::create([
            'nome' => 'Empresa B',
            'cnpj' => '22222222000122',
            'status' => 'ativo',
        ]);

        // Create one user for each company
        $perfilAdminA = Perfil::create([
            'empresa_id' => $empresaA->id,
            'nome' => 'admin',
        ]);

        $perfilAdminB = Perfil::create([
            'empresa_id' => $empresaB->id,
            'nome' => 'admin',
        ]);

        $userA = User::create([
            'name' => 'User A',
            'email' => 'usera@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresaA->id,
            'perfil_id' => $perfilAdminA->id,
            'status' => 'ativo',
        ]);

        $userB = User::create([
            'name' => 'User B',
            'email' => 'userb@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresaB->id,
            'perfil_id' => $perfilAdminB->id,
            'status' => 'ativo',
        ]);

        // Create clients for each company
        $clientesA = [
            Cliente::create([
                'empresa_id' => $empresaA->id,
                'nome' => 'Cliente A1',
                'telefone' => '(11)90000-0001',
                'email' => 'a1@teste.com',
                'observacoes' => null,
                'status' => 'ativo',
            ]),
            Cliente::create([
                'empresa_id' => $empresaA->id,
                'nome' => 'Cliente A2',
                'telefone' => '(11)90000-0002',
                'email' => 'a2@teste.com',
                'observacoes' => null,
                'status' => 'ativo',
            ]),
        ];

        $clienteB = Cliente::create([
            'empresa_id' => $empresaB->id,
            'nome' => 'Cliente B1',
            'telefone' => '(11)90000-0100',
            'email' => 'b1@teste.com',
            'observacoes' => null,
            'status' => 'ativo',
        ]);

        // Authenticate as user A
        Sanctum::actingAs($userA, [], 'sanctum');

        // Request list of clients
        $response = $this->getJson('/api/v1/clientes');

        $response->assertStatus(200);

        // Ensure only empresa A clients are returned
        $response->assertJsonCount(count($clientesA), 'data');

        $returnedEmpresaIds = collect($response->json('data'))->pluck('empresa_id')->unique()->values()->all();
        $this->assertEquals([$empresaA->id], $returnedEmpresaIds, 'Returned clients must belong only to Empresa A');

        // Ensure no client from Empresa B is present
        $returnedNames = collect($response->json('data'))->pluck('nome')->all();
        $this->assertNotContains($clienteB->nome, $returnedNames);
    }
}
