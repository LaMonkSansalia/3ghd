<?php

use App\Http\Controllers\Api\ShowroomController;
use Illuminate\Support\Facades\Route;

Route::prefix('showroom')->middleware(['throttle:60,1'])->group(function () {
    Route::get('categories', [ShowroomController::class, 'categories']);
    Route::get('products', [ShowroomController::class, 'products']);
    Route::get('products/{code}', [ShowroomController::class, 'product']);
});
