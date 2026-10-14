<?php

use App\Http\Controllers\Api\AquaSenseController;
use Illuminate\Support\Facades\Route;

/*
 | AquaSense API v1
 |
 | All telemetry enters through POST /telemetry. The same payload shape is
 | used by the software simulator and by real ESP32 firmware.
 */
Route::prefix('v1')->group(function (): void {
Route::get('/overview', [AquaSenseController::class, 'overview']);
Route::get('/scenarios', [AquaSenseController::class, 'scenarios']);
Route::post('/telemetry', [AquaSenseController::class, 'telemetry']);
Route::post('/devices/claim', [AquaSenseController::class, 'claimDevice']);
Route::post('/alerts/{alert}/acknowledge', [AquaSenseController::class, 'acknowledgeAlert']);

Route::get('/tanks/{tank}', [AquaSenseController::class, 'show']);
Route::get('/tanks/{tank}/readings', [AquaSenseController::class, 'readings']);
Route::get('/tanks/{tank}/alerts', [AquaSenseController::class, 'alerts']);
Route::patch('/tanks/{tank}/settings', [AquaSenseController::class, 'updateSettings']);

Route::post('/tanks/{tank}/simulate/step', [AquaSenseController::class, 'simulateStep']);
Route::post('/tanks/{tank}/simulate/backfill', [AquaSenseController::class, 'simulateBackfill']);
Route::post('/tanks/{tank}/simulate/reset', [AquaSenseController::class, 'simulateReset']);
});
