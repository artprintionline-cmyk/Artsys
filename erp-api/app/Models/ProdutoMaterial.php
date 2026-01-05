<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoMaterial extends Model
{
    protected $table = 'produto_materiais';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        // Produto Vivo
        'material_id',
        // compat / legado
        'material_produto_id',
        'quantidade',
        // Novo: quantidade por base (un/metro/m²)
        'quantidade_base',
    ];

    protected $casts = [
        'quantidade' => 'decimal:4',
        'quantidade_base' => 'decimal:4',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function materialProduto()
    {
        // Preferir a coluna nova; fallback para legado.
        return $this->belongsTo(Produto::class, 'material_id');
    }

    public function materialLegado()
    {
        return $this->belongsTo(Produto::class, 'material_produto_id');
    }
}
