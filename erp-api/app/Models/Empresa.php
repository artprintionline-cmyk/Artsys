<?php

namespace App\Models;

class Empresa extends BaseModel
{
    protected $table = 'empresas';

    protected $fillable = [
        'nome',
        'cnpj',
        'status',
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'empresa_id');
    }
}
