<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Componente extends Model
{
    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
        'tipo',
        'unidade_base',
        'custo_base',
        'status',
    ];

    public function produtoComponentes()
    {
        return $this->hasMany(ProdutoComponente::class, 'componente_id');
    }

    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'produto_componentes', 'componente_id', 'produto_id');
    }
}
