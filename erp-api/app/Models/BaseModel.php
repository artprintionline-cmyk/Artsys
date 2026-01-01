<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\EmpresaScope;
use App\Services\Tenant;
use Illuminate\Support\Facades\Schema;

class BaseModel extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new EmpresaScope());
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (! Tenant::hasEmpresa()) {
                return;
            }

            // Only assign empresa_id automatically if the underlying table
            // actually has an `empresa_id` column to avoid errors on models
            // whose tables don't include that column (eg. empresas).
            if (! Schema::hasColumn($model->getTable(), 'empresa_id')) {
                return;
            }

            if (empty($model->empresa_id)) {
                $model->empresa_id = Tenant::getEmpresaId();
            }
        });
    }
}
