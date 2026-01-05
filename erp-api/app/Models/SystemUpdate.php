<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemUpdate extends Model
{
    protected $table = 'system_updates';

    protected $fillable = [
        'versao_alvo',
        'descricao',
        'status',
        'started_at',
        'finished_at',
        'log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
