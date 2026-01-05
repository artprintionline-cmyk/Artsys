<?php

namespace App\Models;

class ProdutoProcessoProdutivo extends BaseModel
{
    protected $table = 'produto_processos_produtivos';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'processo_produtivo_id',
        'quantidade_base',
    ];

    protected $casts = [
        'quantidade_base' => 'decimal:4',
    ];

    public function processoProdutivo()
    {
        return $this->belongsTo(ProcessoProdutivo::class, 'processo_produtivo_id');
    }
}
