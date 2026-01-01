<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConfiguracao extends Model
{
    protected $table = 'whatsapp_configuracoes';

    protected $fillable = [
        'empresa_id',
        'provedor',
        'numero',
        'token',
        'status',
    ];
}
