<?php

namespace App\Models;

class ProcessoProdutivo extends BaseModel
{
    protected $table = 'processos_produtivos';

    protected $fillable = [
        'empresa_id',
        'nome',
        'unidade_consumo',
        'etapa',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
