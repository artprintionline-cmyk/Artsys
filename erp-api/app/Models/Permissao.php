<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permissao extends Model
{
    protected $table = 'permissoes';

    protected $fillable = [
        'chave',
        'descricao',
    ];

    public function perfis()
    {
        return $this->belongsToMany(Perfil::class, 'perfil_permissao', 'permissao_id', 'perfil_id');
    }
}
