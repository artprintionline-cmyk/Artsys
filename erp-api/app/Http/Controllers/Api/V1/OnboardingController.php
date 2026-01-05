<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingStoreRequest;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Plano;
use App\Models\User;
use App\Services\SaasAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Response;

class OnboardingController extends Controller
{
    public function store(OnboardingStoreRequest $request, SaasAuditoriaService $auditoria): JsonResponse
    {
        $data = $request->validated();

        $existing = User::query()->where('email', $data['admin_email'])->first();
        if ($existing) {
            return response()->json(['message' => 'E-mail já está em uso.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $trialDias = (int) env('SAAS_TRIAL_DIAS', 14);
        if ($trialDias <= 0) {
            $trialDias = 14;
        }

        $planoTrial = Plano::query()->where('ativo', true)->orderBy('id')->first();
        if (! $planoTrial) {
            // plano mínimo para onboarding
            $planoTrial = Plano::create([
                'nome' => 'Trial',
                'preco' => 0,
                'limites' => [
                    'max_usuarios' => 3,
                    'max_os_mes' => 100,
                    'whatsapp' => true,
                    'automacoes' => true,
                ],
                'ativo' => true,
            ]);
        }

        $result = DB::transaction(function () use ($data, $trialDias, $planoTrial) {
            $empresa = Empresa::create([
                'nome' => $data['empresa_nome'],
                'email' => $data['empresa_email'],
                'telefone' => $data['empresa_telefone'] ?? null,
                'status' => true,
            ]);

            $perfilAdmin = $this->bootstrapPerfis((int) $empresa->id);

            $user = User::create([
                'name' => $data['admin_nome'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'empresa_id' => (int) $empresa->id,
                'perfil_id' => $perfilAdmin ? (int) $perfilAdmin->id : null,
                'status' => true,
            ]);

            $inicio = now();
            $fim = now()->addDays($trialDias);

            $assinatura = Assinatura::create([
                'empresa_id' => (int) $empresa->id,
                'plano_id' => (int) $planoTrial->id,
                'status' => 'trial',
                'inicio' => $inicio,
                'fim' => $fim,
            ]);

            $token = $user->createToken('onboarding')->plainTextToken;

            return compact('empresa', 'user', 'assinatura', 'token', 'planoTrial');
        });

        $auditoria->log('onboarding.criado', (int) $result['empresa']->id, (int) $result['user']->id, [
            'plano_id' => (int) $result['planoTrial']->id,
            'status' => 'trial',
            'trial_dias' => $trialDias,
        ]);

        return response()->json([
            'data' => [
                'empresa' => [
                    'id' => (int) $result['empresa']->id,
                    'nome' => (string) $result['empresa']->nome,
                    'email' => (string) ($result['empresa']->email ?? ''),
                    'telefone' => (string) ($result['empresa']->telefone ?? ''),
                    'status' => (bool) $result['empresa']->status,
                ],
                'user' => [
                    'id' => (int) $result['user']->id,
                    'name' => (string) $result['user']->name,
                    'email' => (string) $result['user']->email,
                    'empresa_id' => (int) $result['user']->empresa_id,
                ],
                'assinatura' => [
                    'status' => (string) $result['assinatura']->status,
                    'inicio' => $result['assinatura']->inicio?->toIso8601String(),
                    'fim' => $result['assinatura']->fim?->toIso8601String(),
                    'plano_id' => (int) $result['assinatura']->plano_id,
                ],
                'token' => $result['token'],
            ],
        ], Response::HTTP_CREATED);
    }

    private function bootstrapPerfis(int $empresaId): ?Perfil
    {
        // Perfis padrão
        $admin = Perfil::updateOrCreate(['empresa_id' => $empresaId, 'nome' => 'admin'], []);
        $operacional = Perfil::updateOrCreate(['empresa_id' => $empresaId, 'nome' => 'operacional'], []);
        $financeiro = Perfil::updateOrCreate(['empresa_id' => $empresaId, 'nome' => 'financeiro'], []);
        $leitura = Perfil::updateOrCreate(['empresa_id' => $empresaId, 'nome' => 'leitura'], []);

        // Permissões globais necessárias
        $keys = [
            'dashboard.view',
            'clientes.view', 'clientes.create', 'clientes.edit', 'clientes.delete',
            'produtos.view', 'produtos.create', 'produtos.edit', 'produtos.delete',
            'os.view', 'os.create', 'os.edit', 'os.status', 'os.cancel',
            'financeiro.view', 'financeiro.create', 'financeiro.pay', 'financeiro.delete',
            'estoque.view', 'estoque.adjust',
            'relatorios.view',
            'whatsapp.view', 'whatsapp.send',
            'admin.users.manage',
            'saas.view', 'saas.manage',
        ];

        foreach ($keys as $k) {
            Permissao::firstOrCreate(['chave' => $k], ['descricao' => $k]);
        }

        $byKey = fn (array $k) => Permissao::whereIn('chave', $k)->pluck('id')->all();

        // Admin: acesso total por regra (perfil nome admin); mas mantemos manage para UI.
        $admin->permissoes()->sync($byKey(['admin.users.manage', 'saas.manage', 'saas.view']));

        $operacional->permissoes()->sync($byKey([
            'dashboard.view',
            'clientes.view', 'clientes.create', 'clientes.edit', 'clientes.delete',
            'produtos.view', 'produtos.create', 'produtos.edit', 'produtos.delete',
            'os.view', 'os.create', 'os.edit', 'os.status', 'os.cancel',
            'estoque.view',
        ]));

        $financeiro->permissoes()->sync($byKey([
            'dashboard.view',
            'financeiro.view', 'financeiro.create', 'financeiro.pay', 'financeiro.delete',
            'relatorios.view',
        ]));

        $leitura->permissoes()->sync($byKey([
            'dashboard.view',
            'clientes.view',
            'produtos.view',
            'os.view',
            'financeiro.view',
            'estoque.view',
            'relatorios.view',
            'whatsapp.view',
            'saas.view',
        ]));

        return $admin;
    }
}
