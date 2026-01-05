<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\SystemVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SystemRollbackCommand extends Command
{
    protected $signature = 'system:rollback {--step=1} {--force}';

    protected $description = 'Rollback simples: tenta reverter o último batch de migrations e remove o último registro de versão.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (app()->environment('production') && ! $force) {
            $this->error('Em produção, rode com --force');
            return self::FAILURE;
        }

        $settings = SystemSetting::query()->orderBy('id')->first();
        if (! $settings || ! $settings->installed) {
            $this->error('Sistema não está marcado como instalado.');
            return self::FAILURE;
        }

        $step = (int) $this->option('step');
        if ($step <= 0) {
            $step = 1;
        }

        $last = SystemVersion::query()->orderByDesc('id')->first();
        if (! $last) {
            $this->error('Nenhuma versão registrada para rollback.');
            return self::FAILURE;
        }

        $this->warn('ATENÇÃO: rollback de migrations é limitado e depende do histórico.');
        $this->info('Executando migrate:rollback --step=' . $step . ' ...');

        $exit = Artisan::call('migrate:rollback', ['--step' => $step, '--force' => true]);
        if ($exit !== 0) {
            $this->error('Falha ao executar rollback de migrations.');
            return self::FAILURE;
        }

        $versaoRemovida = (string) $last->versao;
        $last->delete();

        $this->info('Rollback concluído. Versão removida: ' . $versaoRemovida);
        return self::SUCCESS;
    }
}
