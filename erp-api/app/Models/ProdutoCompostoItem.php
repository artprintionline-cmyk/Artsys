<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoCompostoItem extends Model
{
    protected $table = 'produto_composto_itens';

    protected $fillable = [
        'produto_composto_id',
        'produto_id',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
    ];

    public function produtoComposto()
    {
        return $this->belongsTo(ProdutoComposto::class, 'produto_composto_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
