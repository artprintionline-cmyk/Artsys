<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasPagamento extends Model
{
    protected $table = 'saas_pagamentos';

    protected $fillable = [
        'assinatura_id',
        'valor',
        'status',
        'metodo',
        'referencia',
        'pago_em',
        'payload',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'pago_em' => 'datetime',
        'payload' => 'array',
    ];

    public function assinatura()
    {
        return $this->belongsTo(Assinatura::class, 'assinatura_id');
    }
}
