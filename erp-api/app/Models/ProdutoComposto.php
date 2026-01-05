<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoComposto extends Model
{
    protected $table = 'produtos_compostos';

    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
        'preco_base',
        'status',
    ];

    protected $casts = [
        'preco_base' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function itens()
    {
        return $this->hasMany(ProdutoCompostoItem::class, 'produto_composto_id');
    }
}
