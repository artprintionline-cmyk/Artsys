<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'perfil_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }

    /**
     * Retorna as chaves de permissões do usuário (via perfil).
     * Admin pode ser tratado como acesso total.
     *
     * @return array<int,string>
     */
    public function permissoesChaves(): array
    {
        $perfil = $this->perfil;
        if ($perfil && strtolower((string) $perfil->nome) === 'admin') {
            return ['*'];
        }

        if (! $perfil) {
            return [];
        }

        $perfil->loadMissing('permissoes');

        /** @var array<int,string> $keys */
        $keys = $perfil->permissoes->pluck('chave')->map(fn ($v) => (string) $v)->values()->all();

        return $keys;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }
}
