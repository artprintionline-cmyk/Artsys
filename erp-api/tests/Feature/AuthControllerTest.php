<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'cnpj' => null, 'status' => true]);

        User::create([
            'name' => 'Admin',
            'email' => 'admin@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'status' => true,
        ]);

        $res = $this->postJson('api/v1/auth/login', [
            'email' => 'admin@teste.com',
            'password' => 'password',
        ]);

        $res->assertStatus(200);
        $res->assertJsonStructure(['token', 'user', 'empresa_id']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'cnpj' => null, 'status' => true]);

        User::create([
            'name' => 'Admin',
            'email' => 'admin@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'status' => true,
        ]);

        $res = $this->postJson('api/v1/auth/login', [
            'email' => 'admin@teste.com',
            'password' => 'wrong',
        ]);

        $res->assertStatus(401);
        $res->assertJsonFragment(['message' => 'Credenciais inválidas']);
    }

    public function test_login_rejects_inactive_user_with_403(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'cnpj' => null, 'status' => true]);

        User::create([
            'name' => 'Inativo',
            'email' => 'inativo@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'status' => false,
        ]);

        $res = $this->postJson('api/v1/auth/login', [
            'email' => 'inativo@teste.com',
            'password' => 'password',
        ]);

        $res->assertStatus(403);
        $res->assertJsonFragment(['message' => 'Usuário inativo.']);
    }

    public function test_login_rejects_inactive_empresa_with_403(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'cnpj' => null, 'status' => false]);

        User::create([
            'name' => 'Admin',
            'email' => 'admin@teste.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'status' => true,
        ]);

        $res = $this->postJson('api/v1/auth/login', [
            'email' => 'admin@teste.com',
            'password' => 'password',
        ]);

        $res->assertStatus(403);
        $res->assertJsonFragment(['message' => 'Empresa inativa.']);
    }
}
