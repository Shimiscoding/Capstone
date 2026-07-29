<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/home', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/users', [DashboardController::class, 'users'])->name('dashboard.users');
    Route::get('/dashboard/users/create', [DashboardController::class, 'createUser'])->name('dashboard.users.create');
    Route::post('/dashboard/users', [DashboardController::class, 'storeUser'])->name('dashboard.users.store');
    Route::get('/dashboard/users/{user}/edit', [DashboardController::class, 'editUser'])->name('dashboard.users.edit');
    Route::put('/dashboard/users/{user}', [DashboardController::class, 'updateUser'])->name('dashboard.users.update');
    Route::delete('/dashboard/users/{user}', [DashboardController::class, 'destroyUser'])->name('dashboard.users.destroy');
    Route::post('/dashboard/notifications/read', [DashboardController::class, 'markNotificationsRead'])->name('dashboard.notifications.read');
    Route::post('/dashboard/notifications/{notification}/read', [DashboardController::class, 'markNotificationRead'])->name('dashboard.notifications.read-one');
    Route::get('/dashboard/payments', [DashboardController::class, 'payments'])->name('dashboard.payments');
    Route::post('/violations/{violation}/checkout', [PaymentController::class, 'checkout'])->name('payments.checkout');
    Route::get('/payments/{payment}/success', [PaymentController::class, 'success'])->name('payments.success');
    Route::get('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
    Route::get('/dashboard/impounding', [DashboardController::class, 'impounding'])->name('dashboard.impounding');
    Route::get('/dashboard/impounding/export', [DashboardController::class, 'exportImpounding'])->name('dashboard.impounding.export');
    Route::post('/dashboard/impounding/import', [DashboardController::class, 'importImpounding'])->name('dashboard.impounding.import');
    Route::get('/dashboard/settings', [SettingsController::class, 'index'])->name('dashboard.settings');
    Route::put('/dashboard/settings', [SettingsController::class, 'update'])->name('dashboard.settings.update');
    Route::get('/dashboard/settings/backup', [SettingsController::class, 'backup'])->name('dashboard.settings.backup');
    Route::post('/dashboard/settings/clear-cache', [SettingsController::class, 'clearCache'])->name('dashboard.settings.clear-cache');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::post('/paymongo/webhook', [PaymentController::class, 'webhook'])->name('paymongo.webhook');
