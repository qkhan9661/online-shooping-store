<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GarmentController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\RealtimePoseController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::post('/scans', [ScanController::class, 'store']);
    Route::get('/scans/{scan}', [ScanController::class, 'show']);

    Route::get('/measurements/latest', [MeasurementController::class, 'latest']);
    Route::get('/measurements', [MeasurementController::class, 'index']);

    Route::get('/garments', [GarmentController::class, 'index']);
    Route::get('/garments/{garment}', [GarmentController::class, 'show']);
    Route::post('/garments/{garment}/recommend', [GarmentController::class, 'requestRecommendation']);
    Route::get('/garments/{garment}/fit-recommendation', [GarmentController::class, 'fitRecommendation']);
    Route::post('/garments/{garment}/gpu-fit', [GarmentController::class, 'requestGpuFit']);
    Route::post('/garments/{garment}/vton/try-on', [GarmentController::class, 'requestVtonTryOn']);
    Route::post('/garments/{garment}/vton/rebuild', [GarmentController::class, 'queueVtonPipeline']);

    Route::post('/ai/realtime-pose', RealtimePoseController::class);
});
