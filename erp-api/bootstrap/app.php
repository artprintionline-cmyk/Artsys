<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Reenvio automático simples (sem criar nova cobrança)
        $enabled = (bool) env('PIX_REENVIO_AUTOMATICO', true);
        if ($enabled) {
            $dias = (int) env('PIX_REENVIO_DIAS', 2);
            $intervaloHoras = (int) env('PIX_REENVIO_INTERVALO_HORAS', 24);
            $limite = (int) env('PIX_REENVIO_LIMITE', 200);
            $horario = (string) env('PIX_REENVIO_HORARIO', '09:00');

            $schedule
                ->command('financeiro:pix-reenviar-pendentes', [
                    '--dias' => $dias,
                    '--intervalo-horas' => $intervaloHoras,
                    '--limite' => $limite,
                ])
                ->dailyAt($horario);
        }

        // Rotinas por tempo das automações (lembretes/OS parada)
        $schedule
            ->command('automacoes:rotinas')
            ->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'perm' => \App\Http\Middleware\PermissionMiddleware::class,
            'plano' => \App\Http\Middleware\PlanoMiddleware::class,
            'plano.feature' => \App\Http\Middleware\PlanoFeatureMiddleware::class,
            'plano.limite' => \App\Http\Middleware\PlanoLimiteMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            return null;
        });
    })->create();
