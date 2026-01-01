<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    protected $table = 'ordens_servico';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'numero',
        'descricao',
        'valor_total',
        'data_entrega',
        'status_atual',
    ];

    public function itens()
    {
        return $this->hasMany(OsItem::class, 'ordem_servico_id');
    }

    public function historicos()
    {
        return $this->hasMany(OsHistorico::class, 'ordem_servico_id');
    }
}
