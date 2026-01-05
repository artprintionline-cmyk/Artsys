<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMensagem extends Model
{
    protected $table = 'whatsapp_mensagens';

    public $timestamps = false;

    protected $casts = [
        'payload' => 'array',
    ];

    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'cliente_id',
        'numero',
        'mensagem',
        'direcao',
        'tipo',
        'provider_message_id',
        'status',
        'contexto',
        'referencia_id',
        'payload',
        'created_at',
        'updated_at',
    ];
}
