<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMensagem extends Model
{
    protected $table = 'whatsapp_mensagens';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'numero',
        'mensagem',
        'status',
        'contexto',
        'referencia_id',
    ];
}
