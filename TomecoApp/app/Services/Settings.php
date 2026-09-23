<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class Settings
{
    public const DEFAULTS = [
        'general.organization_name' => 'TOMECO', 'general.address' => '', 'general.phone' => '', 'general.email' => '', 'general.timezone' => 'Asia/Manila', 'general.date_format' => 'M j, Y', 'general.violation_prefix' => 'VIO-',
        'violations.late_fee' => 0,
        'violations.catalog' => [['name' => 'Illegal parking', 'fine' => 500, 'enabled' => true], ['name' => 'Road obstruction', 'fine' => 1000, 'enabled' => true]],
        'permissions.admin' => ['manage_users', 'manage_settings', 'record_violations', 'import', 'export'],
        'permissions.supervisor' => ['record_violations', 'export'],
        'notifications.new_violation' => true, 'notifications.overdue_reminder' => true, 'notifications.recipient_email' => '',
        'data.max_upload_mb' => 5, 'data.duplicate_behavior' => 'update', 'data.default_format' => 'xlsx', 'data.history_days' => 90,
        'security.password_min_length' => 8, 'security.session_timeout' => 120, 'security.login_attempts' => 5, 'security.two_factor' => false, 'security.admin_signup_enabled' => true, 'security.api_token_days' => 30, 'security.audit_retention_days' => 365,
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
