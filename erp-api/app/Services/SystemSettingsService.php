<?php

namespace App\Services;

use App\Models\SystemSetting;

class SystemSettingsService
{
    public function isInstalled(): bool
    {
        try {
            $row = SystemSetting::query()->orderBy('id')->first();
            return (bool) ($row?->installed ?? false);
        } catch (\Throwable) {
            // Banco/tabela ainda não existe (primeira execução).
            return false;
        }
    }

    public function markInstalled(): void
    {
        $row = SystemSetting::query()->orderBy('id')->first();
        if (! $row) {
            $row = new SystemSetting();
        }

        $row->installed = true;
        $row->installed_at = now();
        $row->save();
    }
}
