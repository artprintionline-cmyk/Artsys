<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraCabecalho extends BaseModel
{
    protected $table = 'compras_cabecalhos';

    protected $fillable = [
        'empresa_id',
        'data',
        'fornecedor',
        'observacoes',
    ];

    protected $casts = [
        'data' => 'date:Y-m-d',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(Compra::class, 'compra_id');
    }
}
