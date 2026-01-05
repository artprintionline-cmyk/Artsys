<?php

namespace App\Models;

class CompraItem extends BaseModel
{
    protected $table = 'compras_itens';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'nome',
        'unidade_compra',
        'ativo',
        'preco_medio',
        'preco_medio_qtd_base',
        'preco_ultimo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'preco_medio' => 'decimal:4',
        'preco_medio_qtd_base' => 'decimal:4',
        'preco_ultimo' => 'decimal:4',
    ];
}
