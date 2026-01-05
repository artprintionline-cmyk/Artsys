<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaLog extends Model
{
    protected $table = 'auditoria_logs';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'acao',
        'entidade',
        'entidade_id',
        'dados_anteriores',
        'dados_novos',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'dados_anteriores' => 'array',
        'dados_novos' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
