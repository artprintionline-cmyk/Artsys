<?php

namespace App\Models;

class InsumoEquipamento extends BaseModel
{
    protected $table = 'insumos_equipamentos';

    protected $fillable = [
        'empresa_id',
        'nome',
        'unidade_consumo',
        'custo_unitario',
        'equipamento_id',
        'ativo',
    ];

    protected $casts = [
        'custo_unitario' => 'decimal:4',
        'equipamento_id' => 'integer',
        'ativo' => 'boolean',
    ];

}
