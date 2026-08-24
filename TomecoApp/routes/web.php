<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailOtpVerificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupervisorAttendanceController;
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
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [EmailOtpVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-email', [EmailOtpVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/verify-email/resend', [EmailOtpVerificationController::class, 'resend'])->name('verification.resend');

    Route::middleware('verified')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/attendance', [DashboardController::class, 'attendance'])->name('dashboard.attendance');
    Route::post('/dashboard/supervisor/time-in', [SupervisorAttendanceController::class, 'timeIn'])->name('supervisor.time-in');
    Route::post('/dashboard/supervisor/time-out', [SupervisorAttendanceController::class, 'timeOut'])->name('supervisor.time-out');
    Route::get('/dashboard/users', [DashboardController::class, 'users'])->name('dashboard.users');
    Route::get('/dashboard/users/supervisors', [DashboardController::class, 'supervisors'])->name('dashboard.users.supervisors');
    Route::get('/dashboard/users/enforcers', [DashboardController::class, 'enforcers'])->name('dashboard.users.enforcers');
    Route::get('/dashboard/users/admins', [DashboardController::class, 'admins'])->name('dashboard.users.admins');
    Route::get('/dashboard/users/supervisors/create', [DashboardController::class, 'createSupervisor'])->name('dashboard.users.supervisors.create');
    Route::get('/dashboard/users/supervisors/{user}', [DashboardController::class, 'showSupervisor'])->name('dashboard.users.supervisors.show');
    Route::get('/dashboard/users/enforcers/create', [DashboardController::class, 'createEnforcer'])->name('dashboard.users.enforcers.create');
    Route::get('/dashboard/users/admins/create', [DashboardController::class, 'createAdmin'])->name('dashboard.users.admins.create');
    Route::get('/dashboard/users/enforcers/{user}', [DashboardController::class, 'showEnforcer'])->name('dashboard.users.enforcers.show');
    Route::get('/dashboard/users/admins/{user}', [DashboardController::class, 'showAdmin'])->name('dashboard.users.admins.show');
    Route::get('/dashboard/users/create', [DashboardController::class, 'createUser'])->name('dashboard.users.create');
    Route::post('/dashboard/users', [DashboardController::class, 'storeUser'])->name('dashboard.users.store');
    Route::get('/dashboard/users/{user}/edit', [DashboardController::class, 'editUser'])->name('dashboard.users.edit');
    Route::put('/dashboard/users/{user}', [DashboardController::class, 'updateUser'])->name('dashboard.users.update');
    Route::delete('/dashboard/users/{user}', [DashboardController::class, 'destroyUser'])->name('dashboard.users.destroy');
    Route::post('/dashboard/notifications/read', [DashboardController::class, 'markNotificationsRead'])->name('dashboard.notifications.read');
    Route::post('/dashboard/notifications/{notification}/read', [DashboardController::class, 'markNotificationRead'])->name('dashboard.notifications.read-one');
    Route::delete('/dashboard/notifications', [DashboardController::class, 'deleteNotifications'])->name('dashboard.notifications.delete');
    Route::delete('/dashboard/notifications-all', [DashboardController::class, 'deleteAllNotifications'])->name('dashboard.notifications.delete-all');
    Route::get('/dashboard/payments', [DashboardController::class, 'payments'])->name('dashboard.payments');
    Route::get('/dashboard/driver-violators', [DashboardController::class, 'driverViolators'])->name('dashboard.driver-violators');
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
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::post('/paymongo/webhook', [PaymentController::class, 'webhook'])->name('paymongo.webhook');
