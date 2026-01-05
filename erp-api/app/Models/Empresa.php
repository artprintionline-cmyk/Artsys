<?php

namespace App\Models;

class Empresa extends BaseModel
{
    protected $table = 'empresas';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'cnpj',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'empresa_id');
    }
}
