<?php

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Plano;
use App\Models\User;
use App\Models\SystemVersion;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    public function index(Request $request)
    {
        return view('setup', [
            'appVersion' => (string) config('app.version', '1.0.0'),
            'defaultConnection' => (string) config('database.default', 'pgsql'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'db_connection' => 'required|string|in:pgsql,mysql',
            'db_host' => 'required|string|max:190',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:190',
            'db_username' => 'required|string|max:190',
            'db_password' => 'nullable|string|max:190',

            'empresa_nome' => 'required|string|min:2|max:120',
            'empresa_email' => 'required|email|max:190',
            'empresa_telefone' => 'nullable|string|max:40',

            'admin_nome' => 'required|string|min:2|max:120',
            'admin_email' => 'required|email|max:190',
            'admin_password' => 'required|string|min:6|max:190',
        ]);

        // 1) Garantir que existe .env
        $this->ensureEnvFile();

        // 2) Atualizar .env com DB_* e APP_ENV/DEBUG mínimos
        $this->setEnv([
            'DB_CONNECTION' => $validated['db_connection'],
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => (string) $validated['db_port'],
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $validated['db_username'],
            'DB_PASSWORD' => (string) ($validated['db_password'] ?? ''),
        ]);

        // 3) Gerar APP_KEY se vazio
        if (! env('APP_KEY')) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        // 4) Limpar config e reconectar DB
        Artisan::call('config:clear');
        DB::purge();

        // 5) Rodar migrations
        Artisan::call('migrate', ['--force' => true]);

        // 6) Criar empresa inicial + admin + assinatura trial
        $trialDias = (int) env('SAAS_TRIAL_DIAS', 14);
        if ($trialDias <= 0) {
            $trialDias = 14;
        }

        DB::transaction(function () use ($validated, $trialDias) {
            $empresa = Empresa::create([
                'nome' => $validated['empresa_nome'],
                'email' => $validated['empresa_email'],
                'telefone' => $validated['empresa_telefone'] ?? null,
                'status' => true,
            ]);

            // Garantir plano base
            $plano = Plano::query()->where('ativo', true)->orderBy('id')->first();
            if (! $plano) {
                $plano = Plano::create([
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

            $perfilAdmin = Perfil::updateOrCreate(['empresa_id' => $empresa->id, 'nome' => 'admin'], []);

            $user = User::create([
                'name' => $validated['admin_nome'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'empresa_id' => (int) $empresa->id,
                'perfil_id' => (int) $perfilAdmin->id,
                'status' => true,
            ]);

            Assinatura::create([
                'empresa_id' => (int) $empresa->id,
                'plano_id' => (int) $plano->id,
                'status' => 'trial',
                'inicio' => now(),
                'fim' => now()->addDays($trialDias),
            ]);

            // Registrar versão atual como aplicada
            SystemVersion::updateOrCreate(
                ['versao' => (string) config('app.version', '1.0.0')],
                ['descricao' => 'Instalação inicial', 'aplicado_em' => now()]
            );
        });

        /** @var SystemSettingsService $settings */
        $settings = app(SystemSettingsService::class);
        $settings->markInstalled();

        return redirect('/login');
    }

    private function ensureEnvFile(): void
    {
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            return;
        }

        $example = base_path('.env.example');
        if (file_exists($example)) {
            copy($example, $envPath);
            return;
        }

        file_put_contents($envPath, "APP_NAME=ERP\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\n");
    }

    /** @param array<string,string> $pairs */
    private function setEnv(array $pairs): void
    {
        $path = base_path('.env');
        $contents = file_exists($path) ? file_get_contents($path) : '';
        if ($contents === false) {
            $contents = '';
        }

        foreach ($pairs as $key => $value) {
            $value = $this->escapeEnvValue($value);
            $pattern = "/^" . preg_quote($key, '/') . "=.*/m";

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $key . '=' . $value, $contents);
            } else {
                $contents .= (Str::endsWith($contents, "\n") ? '' : "\n") . $key . '=' . $value . "\n";
            }
        }

        file_put_contents($path, $contents);
    }

    private function escapeEnvValue(string $value): string
    {
        // coloca entre aspas se tiver espaços ou caracteres especiais comuns
        if ($value === '') {
            return '""';
        }

        if (preg_match("/\\s|#|\"|'|=/", $value)) {
            $escaped = str_replace('"', '\\"', $value);
            return '"' . $escaped . '"';
        }

        return $value;
    }
}
