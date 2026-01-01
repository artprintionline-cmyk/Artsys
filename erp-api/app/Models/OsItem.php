<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsItem extends Model
{
    protected $table = 'os_itens';

    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'produto_id',
        'quantidade',
        'largura',
        'altura',
        'valor_unitario',
        'valor_total',
        'status',
    ];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
