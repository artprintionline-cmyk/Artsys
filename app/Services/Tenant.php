<?php

namespace App\Services;

class Tenant
{
    protected static ?int $empresaId = null;

    public static function setEmpresaId(?int $id): void
    {
        static::$empresaId = $id;
    }

    public static function getEmpresaId(): ?int
    {
        return static::$empresaId;
    }

    public static function hasEmpresa(): bool
    {
        return !is_null(static::$empresaId);
    }
}
