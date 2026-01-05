<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomacaoExecucao extends Model
{
    protected $table = 'automacoes_execucoes';

    protected $fillable = [
        'empresa_id',
        'automacao_config_id',
        'evento',
        'acao',
        'entidade_tipo',
        'entidade_id',
        'dedupe_key',
        'status',
        'mensagem',
        'payload',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
