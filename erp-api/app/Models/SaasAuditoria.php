<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasAuditoria extends Model
{
    protected $table = 'saas_auditorias';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'acao',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
