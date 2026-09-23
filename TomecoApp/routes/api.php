<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\OfficerLocationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ViolationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::apiResource('users', UserController::class)->except(['store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::put('/auth/signature', [AuthController::class, 'updateSignature']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/violations', [ViolationController::class, 'store']);


    Route::prefix('officer')->group(function (): void {
        Route::post('/location', [OfficerLocationController::class, 'store'])->middleware('throttle:officer-location');

        Route::prefix('attendance')->group(function (): void {
            Route::post('/time-in', [AttendanceController::class, 'timeIn']);
            Route::post('/time-out', [AttendanceController::class, 'timeOut']);
            Route::get('/status', [AttendanceController::class, 'getStatus']);
        });
    });
});
