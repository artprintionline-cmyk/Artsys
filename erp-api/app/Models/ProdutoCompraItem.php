<?php

namespace App\Models;

class ProdutoCompraItem extends BaseModel
{
    protected $table = 'produto_compras_itens';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'compra_item_id',
        'quantidade_base',
    ];

    protected $casts = [
        'quantidade_base' => 'decimal:6',
    ];

    public function compraItem()
    {
        return $this->belongsTo(CompraItem::class, 'compra_item_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
