<?php

use Illuminate\Support\Facades\Route;
use BlueprintExtensions\Auditing\Controllers\AuditingApiController;

/*
|--------------------------------------------------------------------------
| Blueprint Auditing API Routes
|--------------------------------------------------------------------------
|
| These routes provide API endpoints for the blueprint-auditing plugin
| functionality, including marking audits as unrewindable.
|
*/

Route::prefix('api/auditing')->middleware(['api', 'auth:sanctum'])->group(function () {
    
    // Mark audit as unrewindable
    Route::post('audits/{audit}/mark-unrewindable', [AuditingApiController::class, 'markAuditAsUnrewindable'])
        ->name('api.auditing.audits.mark-unrewindable');
    
    // Mark multiple audits as unrewindable
    Route::post('audits/mark-unrewindable-bulk', [AuditingApiController::class, 'markAuditsAsUnrewindableBulk'])
        ->name('api.auditing.audits.mark-unrewindable-bulk');
    
    // Get rewindable audits for a model
    Route::get('models/{modelType}/{modelId}/rewindable-audits', [AuditingApiController::class, 'getRewindableAudits'])
        ->name('api.auditing.models.rewindable-audits');
    
    // Get unrewindable audits for a model
    Route::get('models/{modelType}/{modelId}/unrewindable-audits', [AuditingApiController::class, 'getUnrewindableAudits'])
        ->name('api.auditing.models.unrewindable-audits');
    
    // Get rewindable statistics for a model
    Route::get('models/{modelType}/{modelId}/rewindable-statistics', [AuditingApiController::class, 'getRewindableStatistics'])
        ->name('api.auditing.models.rewindable-statistics');
    
    // Mark audits by criteria as unrewindable
    Route::post('models/{modelType}/{modelId}/mark-audits-unrewindable', [AuditingApiController::class, 'markAuditsByCriteriaAsUnrewindable'])
        ->name('api.auditing.models.mark-audits-unrewindable');
}); 