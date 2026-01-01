<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Services\Tenant;
use Illuminate\Support\Facades\Schema;

class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (! Tenant::hasEmpresa()) {
            return;
        }

        // Only apply the scope when the underlying table has an `empresa_id` column.
        if (! Schema::hasColumn($model->getTable(), 'empresa_id')) {
            return;
        }

        $empresaId = Tenant::getEmpresaId();
        $builder->where($model->getTable() . '.empresa_id', $empresaId);
    }
}
