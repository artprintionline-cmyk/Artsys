<?php

namespace App\Models;

class CustoMaoObra extends BaseModel
{
    protected $table = 'custos_mao_obra';

    protected $fillable = [
        'empresa_id',
        'funcao',
        'custo_hora',
        'centro_custo',
        'ativo',
    ];

    protected $casts = [
        'custo_hora' => 'decimal:2',
        'ativo' => 'boolean',
    ];
}
