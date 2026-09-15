<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Api\V1\LeadIngestionController;

// #19: ingestão idempotente de Leads por integrações externas (Sanctum).
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('leads/ingest', [LeadIngestionController::class, 'store'])
        ->name('api.v1.leads.ingest');
});
