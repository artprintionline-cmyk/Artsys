<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Cliente;
use App\Models\OrdemServico;

class FinanceiroParcelasValidacoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gerar_parcelas_exige_soma_igual_valor_total_os(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Financeiro', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Financeiro',
            'email' => 'finance-valid@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente Finance',
            'telefone' => '111111111',
            'status' => 'ativo',
        ]);

        $ordem = OrdemServico::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-000200',
            'descricao' => 'OS para teste financeiro',
            'data_entrega' => now()->addDays(5)->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 1000.00,
        ]);

        $res = $this->postJson("api/v1/financeiro/{$ordem->id}/gerar", [
            'parcelas' => [
                ['valor' => 400.00, 'vencimento' => '2026-02-10'],
                ['valor' => 500.00, 'vencimento' => '2026-03-10'],
            ],
        ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['message' => 'A soma das parcelas deve ser igual ao valor total da OS.']);
    }

    public function test_gerar_parcelas_nao_permite_gerar_duas_vezes(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Financeiro', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Financeiro',
            'email' => 'finance-dup@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente Finance',
            'telefone' => '111111111',
            'status' => 'ativo',
        ]);

        $ordem = OrdemServico::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-000201',
            'descricao' => 'OS para teste financeiro',
            'data_entrega' => now()->addDays(5)->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 1000.00,
        ]);

        $payload = [
            'parcelas' => [
                ['valor' => 500.00, 'vencimento' => '2026-02-10'],
                ['valor' => 500.00, 'vencimento' => '2026-03-10'],
            ],
        ];

        $this->postJson("api/v1/financeiro/{$ordem->id}/gerar", $payload)->assertStatus(201);

        $res2 = $this->postJson("api/v1/financeiro/{$ordem->id}/gerar", $payload);
        $res2->assertStatus(422);
        $res2->assertJsonFragment(['message' => 'Parcelas já foram geradas para esta OS.']);
    }
}
