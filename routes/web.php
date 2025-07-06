<?php

use Illuminate\Support\Facades\Route;
use Blueprint\Controllers\DashboardController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/blueprint/dashboard', [DashboardController::class, 'index'])->name('blueprint.dashboard');
}); 