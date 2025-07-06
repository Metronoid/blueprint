<?php

use Illuminate\Support\Facades\Route;
use BlueprintExtensions\Auditing\Controllers\AuditingDashboardController;

Route::middleware(['web', 'auth'])->prefix('auditing')->name('auditing.')->group(function () {
    
    // Main dashboard
    Route::get('/', [AuditingDashboardController::class, 'index'])->name('dashboard');
    
    // Audit history and details
    Route::get('/audits', [AuditingDashboardController::class, 'auditHistory'])->name('audits.history');
    Route::get('/audits/{audit}', [AuditingDashboardController::class, 'showAudit'])->name('audits.show');
    
    // Rewind functionality
    Route::get('/rewind', [AuditingDashboardController::class, 'rewindInterface'])->name('rewind.interface');
    Route::post('/rewind', [AuditingDashboardController::class, 'performRewind'])->name('rewind.perform');
    
    // Origin tracking
    Route::get('/origin-tracking', [AuditingDashboardController::class, 'originTracking'])->name('origin.tracking');
    
    // Git-like versioning
    Route::get('/git-versioning', [AuditingDashboardController::class, 'gitVersioning'])->name('git.versioning');
    
    // Analytics and reporting
    Route::get('/analytics', [AuditingDashboardController::class, 'analytics'])->name('analytics');
    
}); 