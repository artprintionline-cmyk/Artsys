<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Events\OsCriadaEvent;
use App\Events\OsStatusMovidaEvent;
use App\Events\FinanceiroGeradoEvent;
use App\Events\PagamentoConfirmadoEvent;
use App\Listeners\DispatchAutomacoesFromEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('whatsapp-os', function (Request $request) {
            $empresaId = $request->attributes->get('empresa_id')
                ?? $request->query('empresa_id')
                ?? '0';

            return Limit::perMinute(60)->by('whatsapp-os:' . (string) $empresaId);
        });

        // Automações (events -> listeners -> jobs). Best-effort e opt-in por empresa.
        Event::listen(OsCriadaEvent::class, [DispatchAutomacoesFromEvent::class, 'onOsCriada']);
        Event::listen(OsStatusMovidaEvent::class, [DispatchAutomacoesFromEvent::class, 'onOsStatusMovida']);
        Event::listen(FinanceiroGeradoEvent::class, [DispatchAutomacoesFromEvent::class, 'onFinanceiroGerado']);
        Event::listen(PagamentoConfirmadoEvent::class, [DispatchAutomacoesFromEvent::class, 'onPagamentoConfirmado']);
    }
}
