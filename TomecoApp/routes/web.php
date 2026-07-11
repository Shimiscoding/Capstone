<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()
        ->view('Mobile_app.Mobilelogin')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
});

Route::get('/home', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/mobile-login', [AuthController::class, 'showMobileLogin'])->name('mobile.login');

    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::post('/mobile-login', [AuthController::class, 'mobileLogin'])->name('mobile.login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

//Mobile App Routes
Route::middleware('auth')->group(function () {
    Route::get('/mobile-home', function () {
        return response()
            ->view('Mobile_app.Mobilehome')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    })->name('mobile.home');

    Route::get('/mobile-account', function () {
        return response()
            ->view('Mobile_app.Mobileaccount')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    })->name('mobile.account');

//Web Routes
    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
