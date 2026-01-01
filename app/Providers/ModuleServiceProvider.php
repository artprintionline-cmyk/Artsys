<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        // aqui podemos carregar providers de módulos automaticamente
    }

    public function boot()
    {
        // publicar rotas/migrations via módulos se necessário
    }
}
