<?php

namespace App\Models;

class ProdutoMaoObra extends BaseModel
{
    protected $table = 'produto_mao_obra';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'custo_mao_obra_id',
        'minutos_base',
    ];

    protected $casts = [
        'minutos_base' => 'decimal:2',
    ];

    public function custoMaoObra()
    {
        return $this->belongsTo(CustoMaoObra::class, 'custo_mao_obra_id');
    }
}
