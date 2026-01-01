<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
        'tipo_medida',
        'largura_padrao',
        'altura_padrao',
        'preco_manual',
        'markup',
        'custo_calculado',
        'preco_final',
        'status',
    ];

    public function produtoComponentes()
    {
        return $this->hasMany(ProdutoComponente::class, 'produto_id');
    }

    public function componentes()
    {
        return $this->belongsToMany(Componente::class, 'produto_componentes', 'produto_id', 'componente_id');
    }
}
