<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compra extends BaseModel
{
    protected $table = 'compras';

    protected $fillable = [
        'empresa_id',
        'compra_id',
        'compra_item_id',
        'data',
        'fornecedor',
        'quantidade',
        'valor_total',
        'custo_unitario',
        'observacoes',
    ];

    protected $casts = [
        'data' => 'date:Y-m-d',
        'quantidade' => 'decimal:4',
        'valor_total' => 'decimal:2',
        'custo_unitario' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CompraItem::class, 'compra_item_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(CompraCabecalho::class, 'compra_id');
    }
}
