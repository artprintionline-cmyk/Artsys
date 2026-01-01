<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'status',
    ];

    protected $hidden = ['password'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
