<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Cliente;
use App\Models\OrdemServico;
use App\Models\ContaReceber;

class FinanceiroFluxoCompletoTest extends TestCase
{
    use RefreshDatabase;

    public function test_gerar_parcelas_a_partir_de_uma_ordem_de_servico(): void
    {
        // Preparação: empresa, usuário, cliente
        $empresa = Empresa::create(['nome' => 'Empresa Financeiro', 'status' => true]);

        $user = User::create([
            'name' => 'User Financeiro',
            'email' => 'finance@test.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'status' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente Finance',
            'telefone' => '111111111',
            'status' => 'ativo',
        ]);

        // Criar Ordem de Serviço com valor_total definido
        $ordem = OrdemServico::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-000100',
            'descricao' => 'OS para teste financeiro',
            'data_entrega' => now()->addDays(5)->toDateString(),
            'status_atual' => 'criada',
            'valor_total' => 1000.00,
        ]);

        // Parcelas a serem geradas
        $parcelas = [
            ['valor' => 500.00, 'vencimento' => '2026-02-10'],
            ['valor' => 500.00, 'vencimento' => '2026-03-10'],
        ];

        // Chamar endpoint de geração (não enviar empresa_id)
        $res = $this->postJson("api/v1/financeiro/{$ordem->id}/gerar", [
            'parcelas' => $parcelas,
        ]);

        $res->assertStatus(201);

        // Validar que 2 registros foram criados para a empresa
        $this->assertDatabaseCount('contas_receber', 2);

        // Validar registros específicos
        foreach ($parcelas as $p) {
            $this->assertDatabaseHas('contas_receber', [
                'empresa_id' => $empresa->id,
                'ordem_servico_id' => $ordem->id,
                'cliente_id' => $cliente->id,
                'valor' => $p['valor'],
                'vencimento' => $p['vencimento'],
                'status' => 'aberta',
            ]);
        }

        // Isolamento por empresa: outra empresa não deve ter parcelas
        $outraEmpresa = Empresa::create(['nome' => 'Outra Empresa', 'status' => true]);

        $this->assertDatabaseCount('contas_receber', 2);
        $this->assertDatabaseMissing('contas_receber', [
            'empresa_id' => $outraEmpresa->id,
        ]);
    }
}
