<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ViolationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('users', UserController::class);
Route::apiResource('violations', ViolationController::class);
