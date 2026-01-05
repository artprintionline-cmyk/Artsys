<?php

use Illuminate\Support\Facades\Route;

Route::middleware([\App\Http\Middleware\EnsureNotInstalled::class])->group(function () {
    Route::get('/setup', [\App\Http\Controllers\SetupController::class, 'index']);
    Route::post('/setup', [\App\Http\Controllers\SetupController::class, 'store']);
});

// SPA fallback: deixa o React Router resolver rotas do frontend.
// Importante: não interceptar rotas de API (/api/*) nem assets (/assets/*).
Route::get('/{any?}', function () {
    $html = file_get_contents(public_path('index.html'));

    return response($html, 200, [
        'Content-Type' => 'text/html; charset=utf-8',
        // Garante que o navegador sempre revalide o HTML da SPA após deploy.
        // Os assets do Vite já são cache-bustados por hash no nome do arquivo.
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
        'X-App-Version' => (string) config('app.version'),
    ]);
})->where('any', '^(?!api|assets|setup).*$');
