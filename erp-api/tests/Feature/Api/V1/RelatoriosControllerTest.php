<?php

namespace Tests\Feature\Api\V1;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FinanceiroLancamento;
use App\Models\OrdemServico;
use App\Models\OsHistorico;
use App\Models\OsItem;
use App\Models\Produto;
use App\Models\User;
use App\Models\Perfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithEmpresa(): User
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

    public function test_relatorio_ordens_servico_filtra_por_empresa(): void
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create(['empresa_id' => $user->empresa_id, 'nome' => 'C1', 'telefone' => '1', 'status' => 'ativo']);

        $os = OrdemServico::create([
            'empresa_id' => $user->empresa_id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-1',
            'descricao' => null,
            'valor_total' => 100.00,
            'data_entrega' => now()->toDateString(),
            'status_atual' => 'finalizada',
        ]);

        OsHistorico::create([
            'empresa_id' => $user->empresa_id,
            'ordem_servico_id' => $os->id,
            'usuario_id' => $user->id,
            'status_anterior' => 'aberta',
            'status_novo' => 'finalizada',
            'observacao' => null,
        ]);

        $otherEmpresa = Empresa::create(['nome' => 'Outra', 'cnpj' => null, 'status' => true]);
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => 'password',
            'empresa_id' => $otherEmpresa->id,
            'status' => true,
        ]);
        $otherCliente = Cliente::create(['empresa_id' => $otherEmpresa->id, 'nome' => 'C2', 'telefone' => '2', 'status' => 'ativo']);
        $otherOs = OrdemServico::create([
            'empresa_id' => $otherEmpresa->id,
            'cliente_id' => $otherCliente->id,
            'numero' => 'OS-X',
            'descricao' => null,
            'valor_total' => 999.00,
            'data_entrega' => now()->toDateString(),
            'status_atual' => 'finalizada',
        ]);
        OsHistorico::create([
            'empresa_id' => $otherEmpresa->id,
            'ordem_servico_id' => $otherOs->id,
            'usuario_id' => $otherUser->id,
            'status_anterior' => 'aberta',
            'status_novo' => 'finalizada',
            'observacao' => null,
        ]);

        $res = $this->getJson('api/v1/relatorios/ordens-servico');
        $res->assertStatus(200);
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.numero_os', 'OS-1');
    }

    public function test_relatorio_faturamento_calcula_totais_por_status(): void
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create(['empresa_id' => $user->empresa_id, 'nome' => 'C1', 'telefone' => '1', 'status' => 'ativo']);
        $os = OrdemServico::create([
            'empresa_id' => $user->empresa_id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-1',
            'descricao' => null,
            'valor_total' => 0,
            'data_entrega' => now()->toDateString(),
            'status_atual' => 'aberta',
        ]);

        FinanceiroLancamento::create([
            'empresa_id' => $user->empresa_id,
            'ordem_servico_id' => $os->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'descricao' => 'A',
            'valor' => 10.00,
            'status' => 'pago',
            'data_vencimento' => now()->toDateString(),
            'data_pagamento' => now()->toDateString(),
        ]);

        FinanceiroLancamento::create([
            'empresa_id' => $user->empresa_id,
            'ordem_servico_id' => $os->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'descricao' => 'B',
            'valor' => 20.00,
            'status' => 'pendente',
            'data_vencimento' => now()->toDateString(),
            'data_pagamento' => null,
        ]);

        FinanceiroLancamento::create([
            'empresa_id' => $user->empresa_id,
            'ordem_servico_id' => $os->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'receber',
            'descricao' => 'C',
            'valor' => 30.00,
            'status' => 'cancelado',
            'data_vencimento' => now()->toDateString(),
            'data_pagamento' => null,
        ]);

        $res = $this->getJson('api/v1/relatorios/faturamento');
        $res->assertStatus(200);
        $res->assertJsonPath('data.total_faturado', 10);
        $res->assertJsonPath('data.total_pendente', 20);
        $res->assertJsonPath('data.total_cancelado', 30);
    }

    public function test_relatorio_produtos_mais_usados_soma_itens_de_os_finalizada(): void
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create(['empresa_id' => $user->empresa_id, 'nome' => 'C1', 'telefone' => '1', 'status' => 'ativo']);
        $produto = Produto::create(['empresa_id' => $user->empresa_id, 'nome' => 'P1', 'tipo_medida' => 'unitario', 'forma_calculo' => 'unitario', 'custo_base' => 1.00, 'preco_base' => 5.00, 'preco' => 5.00, 'status' => 'ativo']);

        $os = OrdemServico::create([
            'empresa_id' => $user->empresa_id,
            'cliente_id' => $cliente->id,
            'numero' => 'OS-1',
            'descricao' => null,
            'valor_total' => 100.00,
            'data_entrega' => now()->toDateString(),
            'status_atual' => 'finalizada',
        ]);

        OsHistorico::create([
            'empresa_id' => $user->empresa_id,
            'ordem_servico_id' => $os->id,
            'usuario_id' => $user->id,
            'status_anterior' => 'aberta',
            'status_novo' => 'finalizada',
            'observacao' => null,
        ]);

        OsItem::create([
            'empresa_id' => $user->empresa_id,
            'ordem_servico_id' => $os->id,
            'produto_id' => $produto->id,
            'quantidade' => 3,
            'largura' => null,
            'altura' => null,
            'valor_unitario' => 5.00,
            'valor_total' => 15.00,
            'status' => 'ativo',
        ]);

        $res = $this->getJson('api/v1/relatorios/produtos-mais-usados');
        $res->assertStatus(200);
        $res->assertJsonPath('data.0.produto.nome', 'P1');
        $res->assertJsonPath('data.0.quantidade_total_utilizada', 3);
    }
}
