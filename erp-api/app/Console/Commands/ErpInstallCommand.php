<?php

namespace App\Console\Commands;

use App\Models\SystemVersion;
use App\Services\SystemSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class ErpInstallCommand extends Command
{
    protected $signature = 'erp:install
        {--force : Executa mesmo se o sistema já estiver instalado}
        {--sqlite : Força SQLite (recomendado para desktop)}
        {--seed : Executa seeders após migrate (default: true)}
        {--no-seed : Não executar seeders}
    ';

    protected $description = 'Instala o ERP localmente (gera APP_KEY, prepara SQLite, migrate e seed)';

    public function handle(SystemSettingsService $settings): int
    {
        $force = (bool) $this->option('force');

        if ($settings->isInstalled() && ! $force) {
            $this->info('Sistema já está instalado.');
            $this->line('Acesse: http://127.0.0.1:8000');
            return self::SUCCESS;
        }

        $this->info('Iniciando instalação...');

        $this->ensureEnvFile();

        // Desktop: SQLite por padrão
        $useSqlite = (bool) $this->option('sqlite') || ((string) env('DB_CONNECTION') === 'sqlite');
        if ($useSqlite) {
            $this->configureSqlite();
        }

        if (! env('APP_KEY')) {
            $this->info('Gerando APP_KEY...');
            Artisan::call('key:generate', ['--force' => true]);
        }

        $this->info('Limpando cache de config...');
        Artisan::call('config:clear');

        $this->info('Rodando migrations...');
        $exit = Artisan::call('migrate', ['--force' => true]);
        if ($exit !== 0) {
            $this->error('Falha ao executar migrations.');
            return self::FAILURE;
        }

        $shouldSeed = ! (bool) $this->option('no-seed');
        if ((bool) $this->option('seed')) {
            $shouldSeed = true;
        }

        if ($shouldSeed) {
            $this->info('Rodando seeders...');
            $exit = Artisan::call('db:seed', ['--force' => true]);
            if ($exit !== 0) {
                $this->error('Falha ao executar seeders.');
                return self::FAILURE;
            }
        }

        $settings->markInstalled();

        SystemVersion::updateOrCreate(
            ['versao' => (string) config('app.version', '1.0.0')],
            ['descricao' => 'Instalação via erp:install', 'aplicado_em' => now()]
        );

        $this->info('Instalação concluída.');
        $this->line('Acesse: http://127.0.0.1:8000');
        $this->line('Login seed (local): admin@teste.com / password');

        return self::SUCCESS;
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

        file_put_contents($envPath, "APP_NAME=ERP\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\n");
    }

    private function configureSqlite(): void
    {
        $dbPath = database_path('database.sqlite');
        if (! file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            touch($dbPath);
        }

        $this->setEnv([
            'APP_URL' => 'http://127.0.0.1:8000',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $dbPath,
            'DB_HOST' => '',
            'DB_PORT' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ]);

        $this->info('SQLite configurado em: ' . $dbPath);
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
        if ($value === '') {
            return '""';
        }

        if (preg_match("/\\s|#|\"|'|=/", $value)) {
            $escaped = str_replace(['\\\\', '"'], ['\\\\\\\\', '\\"'], $value);
            return '"' . $escaped . '"';
        }

        return $value;
    }
}
