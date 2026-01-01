<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Services\Tenant;

class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (Tenant::hasEmpresa()) {
            $empresaId = Tenant::getEmpresaId();
            $builder->where($model->getTable() . '.empresa_id', $empresaId);
        }
    }
}
