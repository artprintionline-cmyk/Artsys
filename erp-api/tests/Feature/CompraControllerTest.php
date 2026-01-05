<?php

namespace Tests\Feature;

use App\Models\CompraItem;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithEmpresa(): User
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

    public function test_store_creates_cabecalho_linhas_and_compra_items(): void
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $payload = [
            'data' => '2026-01-04',
            'fornecedor' => 'Fornecedor A',
            'observacoes' => 'Obs',
            'itens' => [
                [
                    'nome' => 'MDF 18mm',
                    'tipo' => 'material',
                    'unidade_compra' => 'chapa',
                    'quantidade' => 2,
                    'valor_total' => 200,
                ],
                [
                    'nome' => 'Cola branca',
                    'tipo' => 'insumo',
                    'unidade_compra' => 'kg',
                    'quantidade' => 1.5,
                    'valor_total' => 30,
                ],
            ],
        ];

        $res = $this->postJson('api/v1/compras', $payload);
        $res->assertStatus(201);
        $res->assertJsonCount(2, 'data.itens');

        $cabecalhoId = $res->json('data.id');
        $this->assertNotEmpty($cabecalhoId);

        $this->assertDatabaseHas('compras_cabecalhos', [
            'id' => $cabecalhoId,
            'empresa_id' => $user->empresa_id,
            'fornecedor' => 'Fornecedor A',
        ]);

        $this->assertDatabaseHas('compras_itens', [
            'empresa_id' => $user->empresa_id,
            'tipo' => 'material',
            'nome' => 'MDF 18mm',
            'unidade_compra' => 'chapa',
            'ativo' => 1,
        ]);

        $this->assertDatabaseHas('compras_itens', [
            'empresa_id' => $user->empresa_id,
            'tipo' => 'insumo',
            'nome' => 'Cola branca',
            'unidade_compra' => 'kg',
            'ativo' => 1,
        ]);

        // MDF: 200 / 2 = 100.0000
        $this->assertDatabaseHas('compras', [
            'empresa_id' => $user->empresa_id,
            'compra_id' => $cabecalhoId,
            'quantidade' => '2.0000',
            'valor_total' => '200.00',
            'custo_unitario' => '100.0000',
            'fornecedor' => 'Fornecedor A',
        ]);
    }

    public function test_store_reuses_item_by_name_case_insensitive_and_updates_media(): void
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $p1 = [
            'data' => '2026-01-04',
            'itens' => [[
                'nome' => 'MDF 18mm',
                'tipo' => 'material',
                'unidade_compra' => 'chapa',
                'quantidade' => 2,
                'valor_total' => 200,
            ]],
        ];

        $r1 = $this->postJson('api/v1/compras', $p1);
        $r1->assertStatus(201);

        $this->assertEquals(1, CompraItem::where('empresa_id', $user->empresa_id)->count());

        $p2 = [
            'data' => '2026-01-04',
            'itens' => [[
                'nome' => 'mdf 18mm',
                'tipo' => 'material',
                'unidade_compra' => 'chapa',
                'quantidade' => 1,
                'valor_total' => 120,
            ]],
        ];

        $r2 = $this->postJson('api/v1/compras', $p2);
        $r2->assertStatus(201);

        // Ainda 1 item (reuso por nome)
        $this->assertEquals(1, CompraItem::where('empresa_id', $user->empresa_id)->count());

        // média ponderada: (100*2 + 120*1) / 3 = 106.6667
        $item = CompraItem::where('empresa_id', $user->empresa_id)->firstOrFail();
        $this->assertEquals('106.6667', (string) $item->preco_medio);
        $this->assertEquals('3.0000', (string) $item->preco_medio_qtd_base);
        $this->assertEquals('120.0000', (string) $item->preco_ultimo);
    }

    public function test_store_rejects_same_nome_with_different_tipo(): void
    {
        $user = $this->createUserWithEmpresa();
        $this->actingAs($user, 'sanctum');

        $p1 = [
            'data' => '2026-01-04',
            'itens' => [[
                'nome' => 'MDF 18mm',
                'tipo' => 'material',
                'unidade_compra' => 'chapa',
                'quantidade' => 1,
                'valor_total' => 100,
            ]],
        ];

        $this->postJson('api/v1/compras', $p1)->assertStatus(201);

        $p2 = [
            'data' => '2026-01-04',
            'itens' => [[
                'nome' => 'MDF 18mm',
                'tipo' => 'insumo',
                'unidade_compra' => 'chapa',
                'quantidade' => 1,
                'valor_total' => 100,
            ]],
        ];

        $this->postJson('api/v1/compras', $p2)->assertStatus(422);
    }
}
