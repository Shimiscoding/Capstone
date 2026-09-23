<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EnforcerAttendance;
use App\Models\Setting;
use App\Models\SupervisorAttendance;
use App\Models\User;
use App\Models\Violation;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLogger
{
    /** Models representing meaningful actions performed through the website. */
    public const AUDITABLE_TYPES = [
        User::class,
        Violation::class,
        Setting::class,
        EnforcerAttendance::class,
        SupervisorAttendance::class,
    ];

    private const EXCLUDED_FIELDS = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    public static function record(Model $model, string $event): void
    {
        if (! in_array($model::class, self::AUDITABLE_TYPES, true) || ! Schema::hasTable('audit_logs')) {
            return;
        }

        $fields = match ($event) {
            'created' => array_keys($model->getAttributes()),
            'deleted' => array_keys($model->getOriginal()),
            default => array_keys($model->getChanges()),
        };
        $fields = array_values(array_diff($fields, self::EXCLUDED_FIELDS));

        $changes = collect($fields)->mapWithKeys(function (string $field) use ($model, $event): array {
            $old = $event === 'created' ? null : $model->getOriginal($field);
            $new = $event === 'deleted' ? null : $model->getAttribute($field);

            return [$field => [
                'old' => self::normalize($old),
                'new' => self::normalize($new),
            ]];
        })->all();

        if ($changes === []) {
            return;
        }

        $actor = Auth::user();

        AuditLog::create([
            'user_id' => Auth::id(),
            'actor_name' => $actor?->fullName,
            'actor_role' => $actor?->role,
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'subject_label' => self::subjectLabel($model),
            'changes' => $changes,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : Str::limit((string) request()->userAgent(), 1000, ''),
        ]);
    }

    private static function subjectLabel(Model $model): string
    {
        return match (true) {
            $model instanceof User => $model->fullName ?: $model->email,
            $model instanceof Violation => 'Violation ticket #'.str_pad((string) $model->getKey(), 7, '0', STR_PAD_LEFT),
            $model instanceof Setting => Str::headline(Str::after($model->key, '.')).' setting',
            $model instanceof EnforcerAttendance => 'Officer attendance #'.$model->getKey(),
            $model instanceof SupervisorAttendance => 'Supervisor attendance #'.$model->getKey(),
            default => class_basename($model).' #'.$model->getKey(),
        };
    }

    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof Arrayable => $value->toArray(),
            is_array($value) => array_map([self::class, 'normalize'], $value),
            is_object($value) => (string) $value,
            default => $value,
        };
    }
}
