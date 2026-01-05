<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemVersion extends Model
{
    protected $table = 'system_versions';

    protected $fillable = [
        'versao',
        'descricao',
        'aplicado_em',
    ];

    protected $casts = [
        'aplicado_em' => 'datetime',
    ];
}
