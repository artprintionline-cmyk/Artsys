<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assinatura extends Model
{
    protected $table = 'assinaturas';

    protected $fillable = [
        'empresa_id',
        'plano_id',
        'status',
        'inicio',
        'fim',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
    ];

    public function plano()
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
