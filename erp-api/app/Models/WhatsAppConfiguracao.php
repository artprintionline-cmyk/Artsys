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
        'phone_number_id',
        'verify_token',
        'app_secret',
        'api_version',
        'auto_os_em_producao',
        'auto_os_aguardando_pagamento_pix',
        'auto_os_finalizada',
        'status',
    ];
}
