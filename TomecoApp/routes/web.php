<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailOtpVerificationController;
use App\Http\Controllers\EnforcerAttendanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupervisorAttendanceController;
use App\Http\Controllers\SupervisorTeamController;
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
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');
    Route::get('/dashboard/attendance', [DashboardController::class, 'attendance'])->name('dashboard.attendance');
    Route::put('/dashboard/attendance/users/{user}/restrictions', [AdminAttendanceController::class, 'updateRestrictions'])->name('dashboard.attendance.restrictions.update');
    Route::get('/dashboard/attendance/{user}', [DashboardController::class, 'supervisorAttendance'])->name('dashboard.attendance.supervisor');
    Route::put('/dashboard/attendance/supervisors/{attendance}/time-out', [AdminAttendanceController::class, 'updateSupervisorTimeOut'])->name('dashboard.attendance.supervisors.time-out');
    Route::put('/dashboard/attendance/enforcers/{attendance}/time-out', [AdminAttendanceController::class, 'updateEnforcerTimeOut'])->name('dashboard.attendance.enforcers.time-out');
    Route::post('/dashboard/supervisor/time-in', [SupervisorAttendanceController::class, 'timeIn'])->name('supervisor.time-in');
    Route::post('/dashboard/supervisor/time-out', [SupervisorAttendanceController::class, 'timeOut'])->name('supervisor.time-out');
    Route::get('/dashboard/supervisor/attendance', [EnforcerAttendanceController::class, 'index'])->name('supervisor.enforcers.attendance.index');
    Route::post('/dashboard/supervisor/enforcers/{user}/time-in', [EnforcerAttendanceController::class, 'timeIn'])->name('supervisor.enforcers.time-in');
    Route::post('/dashboard/supervisor/enforcers/{user}/time-out', [EnforcerAttendanceController::class, 'timeOut'])->name('supervisor.enforcers.time-out');
    Route::get('/dashboard/supervisor/enforcers/{user}/attendance', [EnforcerAttendanceController::class, 'show'])->name('supervisor.enforcers.attendance');
    Route::get('/dashboard/supervisor/team', [SupervisorTeamController::class, 'index'])->name('supervisor.team');
    Route::post('/dashboard/supervisor/team/{user}', [SupervisorTeamController::class, 'assign'])->name('supervisor.team.assign');
    Route::delete('/dashboard/supervisor/team/{user}', [SupervisorTeamController::class, 'remove'])->name('supervisor.team.remove');
    Route::get('/dashboard/users', [DashboardController::class, 'users'])->name('dashboard.users');
    Route::get('/dashboard/users/supervisors', [DashboardController::class, 'supervisors'])->name('dashboard.users.supervisors');
    Route::get('/dashboard/users/enforcers', [DashboardController::class, 'enforcers'])->name('dashboard.users.enforcers');
    Route::get('/dashboard/users/admins', [DashboardController::class, 'admins'])->name('dashboard.users.admins');
    Route::get('/dashboard/users/supervisors/create', [DashboardController::class, 'createSupervisor'])->name('dashboard.users.supervisors.create');
    Route::get('/dashboard/users/supervisors/{user}', [DashboardController::class, 'showSupervisor'])->name('dashboard.users.supervisors.show');
    Route::post('/dashboard/users/supervisors/{supervisor}/enforcers/{user}', [SupervisorTeamController::class, 'adminAssign'])->name('dashboard.users.supervisors.enforcers.assign');
    Route::delete('/dashboard/users/supervisors/{supervisor}/enforcers/{user}', [SupervisorTeamController::class, 'adminRemove'])->name('dashboard.users.supervisors.enforcers.remove');
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
    Route::get('/dashboard/notifications/feed', [DashboardController::class, 'notificationFeed'])->name('dashboard.notifications.feed');
    Route::post('/dashboard/notifications/{notification}/read', [DashboardController::class, 'markNotificationRead'])->name('dashboard.notifications.read-one');
    Route::delete('/dashboard/notifications', [DashboardController::class, 'deleteNotifications'])->name('dashboard.notifications.delete');
    Route::delete('/dashboard/notifications-all', [DashboardController::class, 'deleteAllNotifications'])->name('dashboard.notifications.delete-all');
    Route::get('/dashboard/violation-records', [DashboardController::class, 'violationRecords'])->name('dashboard.violation-records');
    Route::get('/dashboard/settings', [SettingsController::class, 'index'])->name('dashboard.settings');
    Route::put('/dashboard/settings', [SettingsController::class, 'update'])->name('dashboard.settings.update');
    Route::get('/dashboard/settings/backup', [SettingsController::class, 'backup'])->name('dashboard.settings.backup');
    Route::post('/dashboard/settings/clear-cache', [SettingsController::class, 'clearCache'])->name('dashboard.settings.clear-cache');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
