<?php

use App\Http\Controllers\GetOrderByIdController;
use App\Http\Controllers\PostCreateOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/orders', PostCreateOrderController::class);
Route::get('/orders/{order}', GetOrderByIdController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/provider/submit', [\App\Http\Controllers\ProviderSimulationController::class, 'submit']);
Route::get('/provider/status/{providerOrderId}', [\App\Http\Controllers\ProviderSimulationController::class, 'status']);
