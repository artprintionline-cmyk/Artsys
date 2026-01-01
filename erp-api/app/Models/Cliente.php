<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'empresa_id',
        'nome',
        'telefone',
        'email',
        'observacoes',
        'status',
    ];

    /**
     * Default attribute values.
     * Keeps the model predictable when created programmatically.
     *
     * @var array<string,mixed>
     */
    protected $attributes = [
        'status' => 'ativo',
    ];

    /**
     * Prepare the model for multi-tenant usage.
     * The actual tenant scoping will be applied elsewhere (global scope / middleware).
     */
    // Intentionally left minimal — scoping/relations are added by services or scopes.
}
