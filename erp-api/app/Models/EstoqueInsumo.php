<?php

namespace App\Models;

class EstoqueInsumo extends BaseModel
{
    protected $table = 'estoque_insumos';

    protected $fillable = [
        'empresa_id',
        'insumo_id',
        'quantidade_atual',
        'estoque_minimo',
    ];

    protected $casts = [
        'quantidade_atual' => 'decimal:4',
        'estoque_minimo' => 'decimal:4',
    ];

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
