<?php

namespace App\Models;

class Insumo extends BaseModel
{
    protected $table = 'insumos';

    protected $appends = [
        'estoque_atual',
    ];

    protected $fillable = [
        'empresa_id',
        'nome',
        'sku',
        'custo_unitario',
        'unidade_medida',
        'controla_estoque',
        'ativo',
    ];

    protected $casts = [
        'custo_unitario' => 'decimal:4',
        'controla_estoque' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function estoque()
    {
        return $this->hasOne(EstoqueInsumo::class, 'insumo_id');
    }

    public function getEstoqueAtualAttribute(): float
    {
        $rel = $this->getRelationValue('estoque');
        if ($rel) {
            return (float) $rel->quantidade_atual;
        }

        return 0.0;
    }
}
