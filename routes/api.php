<?php

use App\Presentation\Http\Controllers\CatalogLookupController;
use App\Presentation\Http\Controllers\ExecutionController;
use App\Presentation\Http\Controllers\ItemController;
use App\Presentation\Http\Controllers\ServiceController;
use App\Presentation\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

// Todas as rotas exigem o JWT de funcionário emitido pelo os-service.
Route::middleware('auth.staff')->group(function () {
    // Catálogo: peças (com estoque) e serviços
    Route::prefix('catalog')->group(function () {
        Route::post('/lookup', CatalogLookupController::class);

        Route::prefix('items')->group(function () {
            Route::get('/', [ItemController::class, 'list']);
            Route::post('/', [ItemController::class, 'store']);
            Route::get('/{id}', [ItemController::class, 'show']);
            Route::put('/{id}', [ItemController::class, 'update']);
            Route::delete('/{id}', [ItemController::class, 'destroy']);

            Route::post('/{id}/stock/entry', [StockController::class, 'entry']);
            Route::post('/{id}/stock/withdrawal', [StockController::class, 'withdrawal']);
            Route::get('/{id}/stock/movements', [StockController::class, 'movements']);
        });

        Route::prefix('services')->group(function () {
            Route::get('/', [ServiceController::class, 'list']);
            Route::post('/', [ServiceController::class, 'store']);
            Route::get('/{id}', [ServiceController::class, 'show']);
            Route::put('/{id}', [ServiceController::class, 'update']);
            Route::delete('/{id}', [ServiceController::class, 'destroy']);
        });
    });

    // Fila de execução, diagnóstico e reparo
    Route::prefix('execution')->group(function () {
        Route::get('/queue', [ExecutionController::class, 'queue']);
        Route::get('/metrics/average-execution-time', [ExecutionController::class, 'averageExecutionTime']);
        Route::get('/{serviceOrderId}', [ExecutionController::class, 'show']);
        Route::patch('/{serviceOrderId}/diagnosis', [ExecutionController::class, 'diagnosis']);
        Route::patch('/{serviceOrderId}/repair', [ExecutionController::class, 'repair']);
        Route::patch('/{serviceOrderId}/finish', [ExecutionController::class, 'finish']);
        Route::patch('/{serviceOrderId}/services/{serviceId}/start', [ExecutionController::class, 'startService']);
        Route::patch('/{serviceOrderId}/services/{serviceId}/finish', [ExecutionController::class, 'finishService']);
    });
});
