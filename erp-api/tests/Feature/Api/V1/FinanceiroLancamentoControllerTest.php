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
use App\Models\FinanceiroLancamento;

class FinanceiroLancamentoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_lancamento_from_os_and_scopes_empresa(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Financeiro', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Financeiro',
            'email' => 'finance2@test.com',
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
            'numero' => 'OS-000101',
            'descricao' => 'OS para teste financeiro',
            'data_entrega' => now()->addDays(5)->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 1000.00,
        ]);

        $res = $this->postJson('api/v1/financeiro', [
            'ordem_servico_id' => $ordem->id,
            'tipo' => 'receber',
            'data_vencimento' => '2026-02-10',
        ]);

        $res->assertStatus(201);
        $res->assertJsonStructure(['data' => ['id', 'cliente', 'ordem_servico', 'tipo', 'descricao', 'valor', 'status', 'data_vencimento']]);

        $this->assertDatabaseHas('financeiro_lancamentos', [
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => $ordem->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'valor' => 1000.00,
            'status' => 'pendente',
            'data_vencimento' => '2026-02-10',
        ]);

        // Não pode editar valor manualmente
        $id = $res->json('data.id');
        $resUpdate = $this->putJson("api/v1/financeiro/{$id}", [
            'status' => 'pago',
            'valor' => 1,
        ]);
        $resUpdate->assertStatus(422);
    }

    public function test_update_marks_as_paid_and_sets_date(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Financeiro', 'status' => true]);

        $perfilAdmin = Perfil::create([
            'empresa_id' => $empresa->id,
            'nome' => 'admin',
        ]);

        $user = User::create([
            'name' => 'User Financeiro',
            'email' => 'finance3@test.com',
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
            'numero' => 'OS-000102',
            'descricao' => 'OS para teste financeiro',
            'data_entrega' => now()->addDays(5)->toDateString(),
            'status_atual' => 'aberta',
            'valor_total' => 1000.00,
        ]);

        $l = FinanceiroLancamento::create([
            'empresa_id' => $empresa->id,
            'ordem_servico_id' => $ordem->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'descricao' => 'OS',
            'valor' => 1000.00,
            'status' => 'pendente',
            'data_vencimento' => '2026-02-10',
            'data_pagamento' => null,
        ]);

        $res = $this->putJson("api/v1/financeiro/{$l->id}", [
            'status' => 'pago',
        ]);

        $res->assertStatus(200);
        $this->assertDatabaseHas('financeiro_lancamentos', [
            'id' => $l->id,
            'status' => 'pago',
        ]);
    }
}
