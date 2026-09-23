<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AreaAssignmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnforcerAttendanceController;
use App\Http\Controllers\LocationMonitoringController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupervisorAttendanceController;
use App\Http\Controllers\SupervisorTeamController;
use App\Http\Middleware\EnsureUserCanAccessWebsite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/* Entry and guest authentication routes. */
Route::get('/', fn () => redirect()->route('login'));
Route::get('/home', fn () => Auth::check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->controller(AuthController::class)->group(function (): void {
    Route::get('/login', 'showLogin')->name('login');
    Route::post('/login', 'login')->name('login.store');
    Route::get('/forgot-password', 'showForgotPassword')->name('password.request');
    Route::post('/forgot-password', 'sendPasswordResetLink')->name('password.email');
    Route::get('/reset-password/{token}', 'showResetPassword')->name('password.reset');
    Route::post('/reset-password', 'resetPassword')->name('password.update');
    Route::get('/register', 'showRegister')->name('register');
    Route::post('/register', 'register')->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(EnsureUserCanAccessWebsite::class)->group(function (): void {
        /* Shared profile and dashboard routes. */
        Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function (): void {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
        });
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/audit-logs', [AuditLogController::class, 'index'])->name('dashboard.audit-logs');
        Route::controller(DashboardController::class)->prefix('dashboard')->name('dashboard.')->group(function (): void {
            Route::get('/analytics', 'analytics')->name('analytics');
            Route::get('/violation-records', 'violationRecords')->name('violation-records');
            Route::get('/violation-records/{violation}', 'violationRecord')->name('violation-records.show');
        });

        /* Admin and supervisor location monitoring. */
        Route::controller(LocationMonitoringController::class)->prefix('location-monitoring')->name('location-monitoring.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/officers', 'officers')->name('officers');
            Route::get('/officers/{officer}/history', 'history')->name('history');
        });

        /* Admin and supervisor area assignments. */
        Route::controller(AreaAssignmentController::class)->prefix('area-assignments')->name('area-assignments.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/areas', 'storeArea')->name('areas.store');
            Route::put('/staff/{staff}', 'update')->name('update');
        });

        /* Supervisor attendance and team operations. */
        Route::prefix('dashboard/supervisor')->group(function (): void {
            Route::controller(SupervisorAttendanceController::class)->group(function (): void {
                Route::post('/time-in', 'timeIn')->name('supervisor.time-in');
                Route::post('/time-out', 'timeOut')->name('supervisor.time-out');
            });
            Route::controller(EnforcerAttendanceController::class)->group(function (): void {
                Route::get('/attendance', 'index')->name('supervisor.enforcers.attendance.index');
                Route::post('/enforcers/{user}/time-in', 'timeIn')->name('supervisor.enforcers.time-in');
                Route::post('/enforcers/{user}/time-out', 'timeOut')->name('supervisor.enforcers.time-out');
                Route::get('/enforcers/{user}/attendance', 'show')->name('supervisor.enforcers.attendance');
            });
            Route::controller(SupervisorTeamController::class)->prefix('team')->group(function (): void {
                Route::get('/', 'index')->name('supervisor.team');
                Route::post('/{user}', 'assign')->name('supervisor.team.assign');
                Route::delete('/{user}', 'remove')->name('supervisor.team.remove');
            });
        });

        /* Admin attendance management. */
        Route::prefix('dashboard/attendance')->group(function (): void {
            Route::get('/', [DashboardController::class, 'attendance'])->name('dashboard.attendance');
            Route::put('/users/{user}/restrictions', [AdminAttendanceController::class, 'updateRestrictions'])->name('dashboard.attendance.restrictions.update');
            Route::get('/enforcers/{user}', [EnforcerAttendanceController::class, 'show'])->name('dashboard.attendance.enforcers.show');
            Route::get('/{user}', [DashboardController::class, 'supervisorAttendance'])->name('dashboard.attendance.supervisor');
            Route::put('/supervisors/{attendance}/time-out', [AdminAttendanceController::class, 'updateSupervisorTimeOut'])->name('dashboard.attendance.supervisors.time-out');
            Route::put('/enforcers/{attendance}/time-out', [AdminAttendanceController::class, 'updateEnforcerTimeOut'])->name('dashboard.attendance.enforcers.time-out');
        });

        /* Admin user and team-assignment management. */
        Route::prefix('dashboard/users')->group(function (): void {
            Route::controller(DashboardController::class)->group(function (): void {
                Route::get('/supervisors', 'supervisors')->name('dashboard.users.supervisors');
                Route::get('/enforcers', 'enforcers')->name('dashboard.users.enforcers');
                Route::get('/admins', 'admins')->name('dashboard.users.admins');
                Route::get('/supervisors/create', 'createSupervisor')->name('dashboard.users.supervisors.create');
                Route::get('/supervisors/{user}', 'showSupervisor')->name('dashboard.users.supervisors.show');
                Route::get('/enforcers/create', 'createEnforcer')->name('dashboard.users.enforcers.create');
                Route::get('/enforcers/{user}', 'showEnforcer')->name('dashboard.users.enforcers.show');
                Route::get('/admins/create', 'createAdmin')->name('dashboard.users.admins.create');
                Route::get('/admins/{user}', 'showAdmin')->name('dashboard.users.admins.show');
                Route::get('/create', 'createUser')->name('dashboard.users.create');
                Route::post('/', 'storeUser')->name('dashboard.users.store');
                Route::get('/{user}/edit', 'editUser')->name('dashboard.users.edit');
                Route::put('/{user}', 'updateUser')->name('dashboard.users.update');
                Route::delete('/{user}', 'destroyUser')->name('dashboard.users.destroy');
            });
            Route::controller(SupervisorTeamController::class)->group(function (): void {
                Route::post('/supervisors/{supervisor}/enforcers/{user}', 'adminAssign')->name('dashboard.users.supervisors.enforcers.assign');
                Route::delete('/supervisors/{supervisor}/enforcers/{user}', 'adminRemove')->name('dashboard.users.supervisors.enforcers.remove');
            });
        });

        /* Authenticated user notifications. */
        Route::controller(DashboardController::class)->prefix('dashboard/notifications')->group(function (): void {
            Route::post('/read', 'markNotificationsRead')->name('dashboard.notifications.read');
            Route::get('/feed', 'notificationFeed')->name('dashboard.notifications.feed');
            Route::post('/{notification}/read', 'markNotificationRead')->name('dashboard.notifications.read-one');
            Route::delete('/', 'deleteNotifications')->name('dashboard.notifications.delete');
        });
        Route::delete('/dashboard/notifications-all', [DashboardController::class, 'deleteAllNotifications'])->name('dashboard.notifications.delete-all');

        /* Admin application settings. */
        Route::controller(SettingsController::class)->prefix('dashboard/settings')->group(function (): void {
            Route::get('/', 'index')->name('dashboard.settings');
            Route::put('/', 'update')->name('dashboard.settings.update');
        });
    });
});
