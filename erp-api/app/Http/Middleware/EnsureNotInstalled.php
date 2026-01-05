<?php

namespace App\Http\Middleware;

use App\Services\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var SystemSettingsService $settings */
        $settings = app(SystemSettingsService::class);

        if ($settings->isInstalled()) {
            abort(404);
        }

        return $next($request);
    }
}
