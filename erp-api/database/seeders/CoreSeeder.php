<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Plano;
use App\Models\Assinatura;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure empresa id = 1 exists
        if (! DB::table('empresas')->where('id', 1)->exists()) {
            DB::table('empresas')->insert([
                'id' => 1,
                'nome' => 'Empresa Local',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create or update admin user
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'status' => true,
                'empresa_id' => 1,
            ]
        );

        // Create or update quick-test user
        $quickUser = User::updateOrCreate(
            ['email' => 'teste@teste.com'],
            [
                'name' => 'Quick Test',
                'password' => Hash::make('123'),
                'status' => true,
                'empresa_id' => 1,
            ]
        );

        // Create or update user 'art@gmail.com' with password '123' for local testing
        $artUser = User::updateOrCreate(
            ['email' => 'art@gmail.com'],
            [
                'name' => 'Art Test',
                'password' => Hash::make('123'),
                'status' => true,
                'empresa_id' => 1,
            ]
        );

        // Permissões iniciais (globais)
        $perms = [
            ['chave' => 'dashboard.view', 'descricao' => 'Visualizar dashboard'],

            ['chave' => 'clientes.view', 'descricao' => 'Ver clientes'],
            ['chave' => 'clientes.create', 'descricao' => 'Criar clientes'],
            ['chave' => 'clientes.edit', 'descricao' => 'Editar clientes'],
            ['chave' => 'clientes.delete', 'descricao' => 'Remover clientes'],

            ['chave' => 'produtos.view', 'descricao' => 'Ver produtos'],
            ['chave' => 'produtos.create', 'descricao' => 'Criar produtos'],
            ['chave' => 'produtos.edit', 'descricao' => 'Editar produtos'],
            ['chave' => 'produtos.delete', 'descricao' => 'Remover produtos'],

            ['chave' => 'insumos.view', 'descricao' => 'Ver insumos'],
            ['chave' => 'insumos.create', 'descricao' => 'Criar insumos'],
            ['chave' => 'insumos.edit', 'descricao' => 'Editar insumos'],
            ['chave' => 'insumos.delete', 'descricao' => 'Remover insumos'],

            ['chave' => 'compras.itens.view', 'descricao' => 'Ver itens de compra'],
            ['chave' => 'compras.itens.create', 'descricao' => 'Criar itens de compra'],
            ['chave' => 'compras.itens.edit', 'descricao' => 'Editar itens de compra'],
            ['chave' => 'compras.itens.delete', 'descricao' => 'Remover/desativar itens de compra'],

            ['chave' => 'compras.compras.view', 'descricao' => 'Ver compras'],
            ['chave' => 'compras.compras.create', 'descricao' => 'Registrar compras'],
            ['chave' => 'compras.compras.delete', 'descricao' => 'Remover compras'],

            ['chave' => 'os.view', 'descricao' => 'Ver ordens de serviço'],
            ['chave' => 'os.create', 'descricao' => 'Criar ordens de serviço'],
            ['chave' => 'os.edit', 'descricao' => 'Editar ordens de serviço'],
            ['chave' => 'os.status', 'descricao' => 'Alterar status da OS'],
            ['chave' => 'os.cancel', 'descricao' => 'Cancelar OS'],

            ['chave' => 'financeiro.view', 'descricao' => 'Ver financeiro'],
            ['chave' => 'financeiro.create', 'descricao' => 'Criar lançamentos'],
            ['chave' => 'financeiro.pay', 'descricao' => 'Confirmar pagamento/alterar status'],
            ['chave' => 'financeiro.delete', 'descricao' => 'Remover/cancelar lançamentos'],

            ['chave' => 'estoque.view', 'descricao' => 'Ver estoque'],
            ['chave' => 'estoque.adjust', 'descricao' => 'Ajustar estoque'],

            ['chave' => 'relatorios.view', 'descricao' => 'Ver relatórios'],

            ['chave' => 'whatsapp.view', 'descricao' => 'Ver conversas WhatsApp'],
            ['chave' => 'whatsapp.send', 'descricao' => 'Enviar mensagens WhatsApp'],

            ['chave' => 'admin.users.manage', 'descricao' => 'Gerenciar usuários e perfis'],

            ['chave' => 'saas.view', 'descricao' => 'Ver plano e assinatura'],
            ['chave' => 'saas.manage', 'descricao' => 'Gerenciar assinatura (simulado/manual)'],
        ];

        foreach ($perms as $p) {
            Permissao::updateOrCreate(['chave' => $p['chave']], ['descricao' => $p['descricao']]);
        }

        // Perfis por empresa (empresa 1)
        $admin = Perfil::updateOrCreate(['empresa_id' => 1, 'nome' => 'admin'], []);
        $operacional = Perfil::updateOrCreate(['empresa_id' => 1, 'nome' => 'operacional'], []);
        $financeiro = Perfil::updateOrCreate(['empresa_id' => 1, 'nome' => 'financeiro'], []);
        $leitura = Perfil::updateOrCreate(['empresa_id' => 1, 'nome' => 'leitura'], []);

        $byKey = fn (array $keys) => Permissao::whereIn('chave', $keys)->pluck('id')->all();

        // Admin: acesso total via regra no middleware/model (sem precisar atribuir tudo)
        // Ainda assim, deixamos admin.users.manage para permitir UI condicional.
        $admin->permissoes()->sync($byKey(['admin.users.manage', 'saas.view', 'saas.manage']));

        // Operacional: clientes, produtos, OS
        $operacional->permissoes()->sync($byKey([
            'dashboard.view',
            'clientes.view', 'clientes.create', 'clientes.edit', 'clientes.delete',
            'produtos.view', 'produtos.create', 'produtos.edit', 'produtos.delete',
            'insumos.view', 'insumos.create', 'insumos.edit', 'insumos.delete',
            'compras.itens.view', 'compras.itens.create', 'compras.itens.edit', 'compras.itens.delete',
            'compras.compras.view', 'compras.compras.create', 'compras.compras.delete',
            'os.view', 'os.create', 'os.edit', 'os.status', 'os.cancel',
            'estoque.view',
        ]));

        // Financeiro: financeiro + relatórios
        $financeiro->permissoes()->sync($byKey([
            'dashboard.view',
            'produtos.view',
            'financeiro.view', 'financeiro.create', 'financeiro.pay', 'financeiro.delete',
            'compras.itens.view',
            'compras.compras.view', 'compras.compras.create',
            'relatorios.view',
        ]));

        // Leitura: apenas view
        $leitura->permissoes()->sync($byKey([
            'dashboard.view',
            'clientes.view',
            'produtos.view',
            'insumos.view',
            'compras.itens.view',
            'compras.compras.view',
            'os.view',
            'financeiro.view',
            'estoque.view',
            'relatorios.view',
            'whatsapp.view',
            'saas.view',
        ]));

        // Atribuir perfis aos usuários padrão
        if ($adminUser && empty($adminUser->perfil_id)) {
            $adminUser->perfil_id = $admin->id;
            $adminUser->save();
        }
        if ($quickUser && empty($quickUser->perfil_id)) {
            $quickUser->perfil_id = $operacional->id;
            $quickUser->save();
        }
        if ($artUser && empty($artUser->perfil_id)) {
            $artUser->perfil_id = $financeiro->id;
            $artUser->save();
        }

        // Seed SaaS: plano padrão + assinatura para empresa 1
        $plano = Plano::updateOrCreate(
            ['nome' => 'Padrão'],
            [
                'preco' => 0,
                'limites' => [
                    'max_usuarios' => 0,
                    'max_os_mes' => 0,
                    'whatsapp' => true,
                    'automacoes' => true,
                ],
                'ativo' => true,
            ]
        );

        Assinatura::updateOrCreate(
            ['empresa_id' => 1],
            [
                'plano_id' => (int) $plano->id,
                'status' => 'ativa',
                'inicio' => now()->subDays(1),
                'fim' => now()->addYears(10),
            ]
        );
    }
}
