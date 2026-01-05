<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\SystemUpdate;
use App\Models\SystemVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SystemUpdateCommand extends Command
{
    protected $signature = 'system:update {--descricao=} {--force}';

    protected $description = 'Executa atualização online do sistema (migrations + registro de versão).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        // Segurança básica: não rodar sem --force em production
        if (app()->environment('production') && ! $force) {
            $this->error('Em produção, rode com --force');
            return self::FAILURE;
        }

        $settings = SystemSetting::query()->orderBy('id')->first();
        if (! $settings || ! $settings->installed) {
            $this->error('Sistema não está marcado como instalado. Rode /setup primeiro.');
            return self::FAILURE;
        }

        $version = (string) config('app.version', '1.0.0');
        $descricao = (string) ($this->option('descricao') ?? '');

        $update = SystemUpdate::create([
            'versao_alvo' => $version,
            'descricao' => $descricao !== '' ? $descricao : 'Atualização automática',
            'status' => 'pending',
            'started_at' => now(),
            'log' => null,
        ]);

        $this->info('Rodando migrations...');
        $exit = Artisan::call('migrate', ['--force' => true]);
        if ($exit !== 0) {
            $update->status = 'failed';
            $update->finished_at = now();
            $update->log = (string) Artisan::output();
            $update->save();
            $this->error('Falha ao executar migrations.');
            return self::FAILURE;
        }

        SystemVersion::updateOrCreate(
            ['versao' => $version],
            [
                'descricao' => $descricao !== '' ? $descricao : 'Atualização automática',
                'aplicado_em' => now(),
            ]
        );

        $update->status = 'applied';
        $update->finished_at = now();
        $update->log = (string) Artisan::output();
        $update->save();

        $this->info('Atualização concluída. Versão aplicada: ' . $version);
        return self::SUCCESS;
    }
}
