<?php

use Illuminate\Support\Facades\Route;
use Blueprint\Controllers\DashboardController;

Route::middleware(['web'])->group(function () {
    Route::get('/blueprint/dashboard', [DashboardController::class, 'index'])->name('blueprint.dashboard');
    Route::get('/blueprint/dashboard/widgets/{widget}/data', [DashboardController::class, 'widgetData'])->name('blueprint.dashboard.widget');
}); 