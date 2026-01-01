<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Modules\Core\AuthController;
use App\Http\Controllers\Api\V1\ClienteController;

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware(['auth:sanctum', 'tenant']);

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

        Route::get('me', function (Request $request) {
            return $request->user();
        });

        Route::get('teste-tenant', function (Request $request) {
            $user = $request->user();
            $empresaId = $request->attributes->get('empresa_id');

            return response()->json([
                'user_id' => $user?->id,
                'empresa_id' => $empresaId,
                'mensagem' => 'Tenant OK',
            ]);
        });

        // Clientes CRUD (multi-tenant)
        Route::apiResource('clientes', ClienteController::class);

    });

});
