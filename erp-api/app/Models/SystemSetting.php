<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'installed',
        'installed_at',
    ];

    protected $casts = [
        'installed' => 'boolean',
        'installed_at' => 'datetime',
    ];
}
