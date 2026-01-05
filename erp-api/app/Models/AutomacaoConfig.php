<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomacaoConfig extends Model
{
    protected $table = 'automacoes_config';

    protected $fillable = [
        'empresa_id',
        'evento',
        'acao',
        'ativo',
        'parametros',
    ];

    protected $casts = [
        'ativo' => 'bool',
        'parametros' => 'array',
    ];
}
