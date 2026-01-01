<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Registrar bindings específicos de módulos aqui.
    }

    public function boot()
    {
        // Carregar rotas, migrations e views de módulos quando necessário.
    }
}
