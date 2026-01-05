<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoqueProduto extends Model
{
    protected $table = 'estoque_produtos';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'quantidade_atual',
        'estoque_minimo',
    ];

    protected $casts = [
        'quantidade_atual' => 'decimal:2',
        'estoque_minimo' => 'decimal:2',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
