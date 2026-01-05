<?php

namespace App\Models;

class Equipamento extends BaseModel
{
    protected $table = 'equipamentos';

    protected $fillable = [
        'empresa_id',
        'nome',
        'local_producao',
        'tipo_depreciacao',
        'valor_equipamento',
        'vida_util_meses',
        'rendimento_total',
        'custo_por_uso',
        'ativo',
    ];

    protected $casts = [
        'valor_equipamento' => 'decimal:2',
        'vida_util_meses' => 'integer',
        'rendimento_total' => 'integer',
        'custo_por_uso' => 'decimal:6',
        'ativo' => 'boolean',
    ];
}
