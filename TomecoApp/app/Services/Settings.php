<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class Settings
{
    public const DEFAULTS = [
        'general.organization_name' => 'TOMECO', 'general.address' => '', 'general.phone' => '', 'general.email' => '', 'general.timezone' => 'Asia/Manila', 'general.date_format' => 'M j, Y', 'general.violation_prefix' => 'VIO-', 'general.payment_prefix' => 'PAY-', 'general.impound_prefix' => 'IMP-',
        'violations.payment_deadline_days' => 30, 'violations.late_fee' => 0, 'violations.impounding_enabled' => true,
        'violations.catalog' => [['name' => 'Illegal parking', 'fine' => 500, 'enabled' => true], ['name' => 'Road obstruction', 'fine' => 1000, 'enabled' => true]],
        'impounding.locations' => ['Main Yard'], 'impounding.vehicle_types' => ['Car', 'Motorcycle'], 'impounding.daily_storage_fee' => 100, 'impounding.grace_period_days' => 1, 'impounding.maximum_holding_days' => 90, 'impounding.release_requirements' => 'Paid violation and valid proof of ownership',
        'payments.online_enabled' => true, 'payments.methods' => ['card', 'gcash', 'paymaya'], 'payments.receipt_name' => 'TOMECO', 'payments.refunds_enabled' => false,
        'permissions.admin' => ['manage_users', 'manage_settings', 'record_violations', 'view_payments', 'import', 'export', 'release_vehicle'],
        'permissions.officer' => ['record_violations', 'view_payments', 'export', 'release_vehicle'], 'permissions.driver' => ['view_payments'],
        'notifications.new_violation' => true, 'notifications.payment_confirmation' => true, 'notifications.release_notice' => true, 'notifications.overdue_reminder' => true, 'notifications.failed_payment' => true, 'notifications.recipient_email' => '',
        'data.max_upload_mb' => 5, 'data.duplicate_behavior' => 'update', 'data.default_format' => 'xlsx', 'data.history_days' => 90,
        'security.password_min_length' => 8, 'security.session_timeout' => 120, 'security.login_attempts' => 5, 'security.two_factor' => false, 'security.api_token_days' => 30, 'security.audit_retention_days' => 365,
        'maintenance.enabled' => false, 'maintenance.data_retention_days' => 1825, 'maintenance.backup_frequency' => 'manual',
    ];

    public function all(): array
    {
        return Cache::remember('system-settings', 300, fn () => array_replace(self::DEFAULTS, Setting::query()->pluck('value', 'key')->all()));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('system-settings');
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->get('permissions.'.$user->role, []), true);
    }
}
