<?php

namespace App\Services;

use App\Models\SystemSetting;

class SystemSettingsService
{
    public function isInstalled(): bool
    {
        $row = SystemSetting::query()->orderBy('id')->first();
        return (bool) ($row?->installed ?? false);
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
