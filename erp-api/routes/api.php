<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum','tenant');

    Route::middleware(['auth:sanctum','tenant'])->group(function () {
        Route::get('me', function (Request $request) {
            return $request->user();
        });

        Route::get('teste-tenant', function (Request $request) {
            $user = $request->user();
            $empresaId = $request->attributes->get('empresa_id') ?? null;
            return response()->json([
                'user_id' => $user ? $user->id : null,
                'empresa_id' => $empresaId,
                'mensagem' => 'Tenant OK',
            ]);
        });
        // Clientes CRUD (multi-tenant)
        Route::get('clientes', [App\Http\Controllers\Api\V1\ClienteController::class, 'index']);
        Route::post('clientes', [App\Http\Controllers\Api\V1\ClienteController::class, 'store']);
        Route::get('clientes/{id}', [App\Http\Controllers\Api\V1\ClienteController::class, 'show']);
        Route::put('clientes/{id}', [App\Http\Controllers\Api\V1\ClienteController::class, 'update']);
        Route::delete('clientes/{id}', [App\Http\Controllers\Api\V1\ClienteController::class, 'destroy']);

        // Produtos CRUD + composição e custo (multi-tenant)
        $produtoController = App\Http\Controllers\Api\V1\ProdutoController::class;

        Route::get('produtos', [$produtoController, 'index']);
        Route::post('produtos', [$produtoController, 'store']);
        Route::get('produtos/{id}', [$produtoController, 'show']);
        Route::put('produtos/{id}', [$produtoController, 'update']);
        Route::delete('produtos/{id}', [$produtoController, 'destroy']);

        Route::post('produtos/{id}/componentes', [$produtoController, 'adicionarComponente']);
        Route::delete('produtos/{id}/componentes/{componenteId}', [$produtoController, 'removerComponente']);
        Route::post('produtos/{id}/recalcular-custo', [$produtoController, 'recalcularCusto']);

        // Ordens de Serviço (OS) CRUD + itens e status
        $osController = App\Http\Controllers\Api\V1\OrdemServicoController::class;

        Route::get('ordens-servico', [$osController, 'index']);
        Route::post('ordens-servico', [$osController, 'store']);
        Route::get('ordens-servico/{id}', [$osController, 'show']);

        Route::post('ordens-servico/{id}/itens', [$osController, 'adicionarItem']);
        Route::delete('ordens-servico/{id}/itens/{itemId}', [$osController, 'removerItem']);

        Route::post('ordens-servico/{id}/status', [$osController, 'atualizarStatus']);
        Route::delete('ordens-servico/{id}', [$osController, 'destroy']);

        // Financeiro (contas a receber)
        $financeiroController = App\Http\Controllers\Api\V1\FinanceiroController::class;

        Route::get('financeiro', [$financeiroController, 'index']);
        Route::post('financeiro/{ordemServicoId}/gerar', [$financeiroController, 'gerar']);
        
        // Dashboard summary
        Route::get('dashboard/summary', [App\Http\Controllers\Api\V1\DashboardController::class, 'summary']);
    });
});
