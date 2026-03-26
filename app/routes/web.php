<?php

use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->patch('/studio/tour-complete', [TourController::class, 'complete'])->name('tour.complete');
