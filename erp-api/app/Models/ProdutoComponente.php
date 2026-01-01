<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoComponente extends Model
{
    protected $fillable = [
        'empresa_id',
        'produto_id',
        'componente_id',
        'quantidade',
        'custo_unitario',
        'custo_total',
        'descricao',
        'status',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function componente()
    {
        return $this->belongsTo(Componente::class, 'componente_id');
    }
}
