<?php

use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

Route::prefix('logs')->group(function () {
    Route::get('/',      [LogController::class, 'index']);
    Route::get('/{id}',  [LogController::class, 'show']);
    Route::post('/',     [LogController::class, 'store']);
    Route::patch('/{id}',[LogController::class, 'update']);
    Route::delete('/{id}',[LogController::class, 'destroy']);
});