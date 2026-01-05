<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceiroLancamento extends Model
{
    protected $table = 'financeiro_lancamentos';

    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'cliente_id',
        'tipo',
        'descricao',
        'valor',
        'status',
        'data_vencimento',
        'data_pagamento',
    ];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
