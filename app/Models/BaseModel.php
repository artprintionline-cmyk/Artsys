<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\EmpresaScope;
use App\Services\Tenant;

class BaseModel extends Model
{
    /**
     * Aplica o global scope para filtrar por empresa_id quando disponível.
     */
    protected static function booted()
    {
        static::addGlobalScope(new EmpresaScope());
    }

    /**
     * Ao criar, garante que `empresa_id` seja preenchido se disponível.
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Tenant::hasEmpresa() && empty($model->empresa_id)) {
                $model->empresa_id = Tenant::getEmpresaId();
            }
        });
    }
}
