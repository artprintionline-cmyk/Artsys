<?php

namespace App\Models;

class ProdutoEquipamento extends BaseModel
{
    protected $table = 'produto_equipamentos';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'equipamento_id',
        'quantidade_base',
    ];

    protected $casts = [
        'quantidade_base' => 'decimal:4',
    ];

    public function equipamento()
    {
        return $this->belongsTo(Equipamento::class, 'equipamento_id');
    }
}
