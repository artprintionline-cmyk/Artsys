<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = [
        'nome',
        'preco',
        'limites',
        'ativo',
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'limites' => 'array',
        'ativo' => 'boolean',
    ];
}
