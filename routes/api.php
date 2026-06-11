<?php

use App\Http\Controllers\Api\ProdukApiController;
use App\Http\Controllers\Api\TelegramOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/produk', [ProdukApiController::class, 'index'])
    ->name('api.produk.index');

Route::post('/telegram/orders', [TelegramOrderController::class, 'store'])
    ->name('api.telegram.orders.store');
