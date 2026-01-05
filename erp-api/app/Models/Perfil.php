<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'perfis';

    protected $fillable = [
        'empresa_id',
        'nome',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuarios()
    {
        return $this->hasMany(User::class, 'perfil_id');
    }

    public function permissoes()
    {
        return $this->belongsToMany(Permissao::class, 'perfil_permissao', 'perfil_id', 'permissao_id');
    }
}
