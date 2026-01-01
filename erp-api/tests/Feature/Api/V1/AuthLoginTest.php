<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Empresa;
use Illuminate\Support\Facades\Hash;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_token_user_and_empresa_id()
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

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@teste.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user',
                'empresa_id',
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals($empresa->id, $response->json('empresa_id'));
    }
}
