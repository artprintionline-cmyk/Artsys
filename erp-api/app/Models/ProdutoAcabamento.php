<?php

namespace App\Models;

class ProdutoAcabamento extends BaseModel
{
    protected $table = 'produto_acabamentos';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'acabamento_id',
        'quantidade_base',
    ];

    protected $casts = [
        'quantidade_base' => 'decimal:6',
    ];

    public function acabamento()
    {
        return $this->belongsTo(Acabamento::class, 'acabamento_id');
    }
}
