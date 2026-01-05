<?php

namespace App\Models;

class Acabamento extends BaseModel
{
    protected $table = 'acabamentos';

    protected $fillable = [
        'empresa_id',
        'nome',
        'unidade_consumo',
        'custo_unitario',
        'ativo',
    ];

    protected $casts = [
        'custo_unitario' => 'decimal:6',
        'ativo' => 'boolean',
    ];
}
