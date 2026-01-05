<?php

namespace App\Models;

class ProdutoInsumo extends BaseModel
{
    protected $table = 'produto_insumos';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'insumo_id',
        'quantidade_base',
    ];

    protected $casts = [
        'quantidade_base' => 'decimal:4',
    ];

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
