<?php

namespace App\Models;

class CustoFixo extends BaseModel
{
    protected $table = 'custos_fixos';

    protected $fillable = [
        'empresa_id',
        'descricao',
        'valor_mensal',
        'centro_custo',
        'ativo',
    ];

    protected $casts = [
        'valor_mensal' => 'decimal:2',
        'ativo' => 'boolean',
    ];
}
