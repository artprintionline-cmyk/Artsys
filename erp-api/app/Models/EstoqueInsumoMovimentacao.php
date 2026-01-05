<?php

namespace App\Models;

class EstoqueInsumoMovimentacao extends BaseModel
{
    protected $table = 'estoque_insumo_movimentacoes';

    protected $fillable = [
        'empresa_id',
        'insumo_id',
        'tipo',
        'quantidade',
        'origem',
        'origem_id',
        'motivo',
    ];

    protected $casts = [
        'quantidade' => 'decimal:4',
    ];

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
