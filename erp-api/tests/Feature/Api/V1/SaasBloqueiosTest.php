<?php

namespace Tests\Feature\Api\V1;

use App\Models\Assinatura;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Plano;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasBloqueiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_expirado_bloqueia_post_mas_permite_get(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Teste', 'status' => true]);

        $perfilAdmin = Perfil::create(['empresa_id' => $empresa->id, 'nome' => 'admin']);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@empresa.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $plano = Plano::create([
            'nome' => 'Trial',
            'preco' => 0,
            'limites' => ['max_os_mes' => 0, 'max_usuarios' => 0, 'whatsapp' => true, 'automacoes' => true],
            'ativo' => true,
        ]);

        Assinatura::create([
            'empresa_id' => $empresa->id,
            'plano_id' => $plano->id,
            'status' => 'trial',
            'inicio' => now()->subDays(10),
            'fim' => now()->subDay(),
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('api/v1/ordens-servico')->assertStatus(200);

        $res = $this->postJson('api/v1/clientes', [
            'nome' => 'Cliente X',
            'telefone' => '000',
            'status' => 'ativo',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'SAAS_READ_ONLY');
    }

    public function test_limite_os_mes_bloqueia_criacao_quando_atingido(): void
    {
        $empresa = Empresa::create(['nome' => 'Empresa Limite', 'status' => true]);
        $perfilAdmin = Perfil::create(['empresa_id' => $empresa->id, 'nome' => 'admin']);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@limite.com',
            'password' => 'password',
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilAdmin->id,
            'status' => true,
        ]);

        $plano = Plano::create([
            'nome' => 'Básico',
            'preco' => 0,
            'limites' => ['max_os_mes' => 1, 'max_usuarios' => 0, 'whatsapp' => true, 'automacoes' => true],
            'ativo' => true,
        ]);

        Assinatura::create([
            'empresa_id' => $empresa->id,
            'plano_id' => $plano->id,
            'status' => 'ativa',
            'inicio' => now()->subDay(),
            'fim' => now()->addMonth(),
        ]);

        $this->actingAs($user, 'sanctum');

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente A',
            'telefone' => '000',
            'status' => 'ativo',
        ]);

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Produto X',
            'sku' => null,
            'preco' => 100.00,
            'forma_calculo' => 'unitario',
            'custo_base' => 50.00,
            'preco_base' => 100.00,
            'tipo_medida' => 'unitario',
            'preco_manual' => null,
            'markup' => null,
            'custo_calculado' => 0,
            'preco_final' => 100.00,
            'status' => 'ativo',
        ]);

        $payloadOs = [
            'cliente_id' => $cliente->id,
            'data_entrega' => now()->addDays(7)->toDateString(),
            'descricao' => 'OS 1',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
            ]],
        ];

        $this->postJson('api/v1/ordens-servico', $payloadOs)->assertStatus(201);

        $res2 = $this->postJson('api/v1/ordens-servico', $payloadOs);
        $res2->assertStatus(403);
        $res2->assertJsonPath('code', 'SAAS_LIMIT_REACHED');
        $res2->assertJsonPath('limit', 'max_os_mes');
    }
}
