<?php

use Illuminate\Support\Facades\Route;

// Serve the built React SPA from public/index.html for root and any path
Route::get('/{any?}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');
