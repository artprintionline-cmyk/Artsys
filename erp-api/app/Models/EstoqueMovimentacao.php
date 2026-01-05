<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstoqueMovimentacao extends Model
{
    protected $table = 'estoque_movimentacoes';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'tipo',
        'quantidade',
        'origem',
        'origem_id',
        'motivo',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
