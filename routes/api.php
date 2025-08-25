<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Order endpoints
Route::apiResource('orders', OrderController::class)->only(['store', 'show']);
Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);

// Location endpoints
Route::get('locations/{location}/products', [LocationController::class, 'products']);
Route::post('locations/{location}/estimate-ready-at', [LocationController::class, 'estimateReadyTime']);

// Company endpoints
Route::get('companies/{company}/products', [CompanyController::class, 'products']);
Route::get('companies/{company}/locations', [CompanyController::class, 'locations']);
