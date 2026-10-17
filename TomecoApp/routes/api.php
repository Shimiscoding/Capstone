<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OfficerLocationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ViolationController;
use App\Http\Controllers\Api\ViolationTypeController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Middleware\EnsureApiAccountActive;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', EnsureApiAccountActive::class])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::post('/auth/profile-photo', [ProfilePhotoController::class, 'update']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::put('/auth/signature', [AuthController::class, 'updateSignature']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/violations', [ViolationController::class, 'store']);
    Route::get('/violation-types', [ViolationTypeController::class, 'index']);
    Route::get('/violation-form-options', [ViolationTypeController::class, 'formOptions']);

    Route::prefix('officer')->group(function (): void {
        Route::post('/location', [OfficerLocationController::class, 'store'])->middleware('throttle:officer-location');

        Route::prefix('attendance')->group(function (): void {
            Route::post('/time-in', [AttendanceController::class, 'timeIn']);
            Route::post('/time-out', [AttendanceController::class, 'timeOut']);
            Route::get('/status', [AttendanceController::class, 'getStatus']);
        });
    });
});
