<?php

namespace App\Http\Controllers;

use App\Models\ImpoundedVehicle;
use App\Models\Payment;
use App\Models\User;
use App\Models\Violation;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request, Settings $settings): View
    {
        $this->admin($request);

        return view('dashboard.settings', ['settings' => $settings->all(), 'activeTab' => $request->query('tab', 'general')]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $this->admin($request);
        $section = $request->validate(['section' => ['required', Rule::in(['general', 'violations', 'impounding', 'payments', 'permissions', 'notifications', 'data', 'security', 'maintenance'])]])['section'];
        foreach ($this->booleanFields($section) as $field) {
            $request->merge([$field => $request->boolean($field)]);
        }
        $values = $this->validatedSection($request, $section);
        if ($section === 'violations') {
            $values['catalog'] = collect(preg_split('/\r\n|\r|\n/', $request->string('catalog_text')->toString()))->filter()->map(function ($line): array {
                [$name, $fine] = array_pad(array_map('trim', explode('|', $line, 2)), 2, 0);

                return ['name' => $name, 'fine' => (float) $fine, 'enabled' => true];
            })->values()->all();
            unset($values['catalog_text']);
        }
        $settings->putMany(collect($values)->mapWithKeys(fn ($value, $key) => [$section.'.'.$key => $value])->all());

        return to_route('dashboard.settings', ['tab' => $section])->with('success', ucfirst($section).' settings saved.');
    }

    public function backup(Request $request, Settings $settings): Response
    {
        $this->admin($request);
        $payload = ['created_at' => now()->toIso8601String(), 'settings' => $settings->all(), 'users' => User::all(), 'violations' => Violation::all(), 'payments' => Payment::all(), 'impounded_vehicles' => ImpoundedVehicle::all()];

        return response(json_encode($payload, JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="tomeco-backup-'.now()->format('Y-m-d-His').'.json"']);
    }

    public function clearCache(Request $request): RedirectResponse
    {
        $this->admin($request);
        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        return to_route('dashboard.settings', ['tab' => 'maintenance'])->with('success', 'Application and view caches cleared.');
    }

    private function validatedSection(Request $request, string $section): array
    {
        return match ($section) {
            'general' => $request->validate(['organization_name' => 'required|string|max:100', 'address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'timezone' => 'required|timezone', 'date_format' => 'required|string|max:30', 'violation_prefix' => 'required|string|max:10', 'payment_prefix' => 'required|string|max:10', 'impound_prefix' => 'required|string|max:10']),
            'violations' => $request->validate(['payment_deadline_days' => 'required|integer|min:0|max:365', 'late_fee' => 'required|numeric|min:0', 'impounding_enabled' => 'boolean', 'catalog_text' => 'required|string']),
            'impounding' => $request->validate(['locations' => 'required|array|min:1', 'locations.*' => 'required|string|max:100', 'vehicle_types' => 'required|array|min:1', 'vehicle_types.*' => 'required|string|max:50', 'daily_storage_fee' => 'required|numeric|min:0', 'grace_period_days' => 'required|integer|min:0', 'maximum_holding_days' => 'required|integer|min:1', 'release_requirements' => 'nullable|string|max:1000']),
            'payments' => $request->validate(['online_enabled' => 'boolean', 'methods' => 'required|array|min:1', 'methods.*' => Rule::in(['card', 'gcash', 'paymaya']), 'receipt_name' => 'required|string|max:100', 'refunds_enabled' => 'boolean']),
            'permissions' => $request->validate(['admin' => 'array', 'officer' => 'array', 'driver' => 'array']),
            'notifications' => $request->validate(['new_violation' => 'boolean', 'payment_confirmation' => 'boolean', 'release_notice' => 'boolean', 'overdue_reminder' => 'boolean', 'failed_payment' => 'boolean', 'recipient_email' => 'nullable|email']),
            'data' => $request->validate(['max_upload_mb' => 'required|integer|min:1|max:20', 'duplicate_behavior' => ['required', Rule::in(['update', 'skip'])], 'default_format' => ['required', Rule::in(['xlsx', 'xls', 'csv'])], 'history_days' => 'required|integer|min:1|max:3650']),
            'security' => $request->validate(['password_min_length' => 'required|integer|min:8|max:64', 'session_timeout' => 'required|integer|min:5|max:1440', 'login_attempts' => 'required|integer|min:3|max:20', 'two_factor' => 'boolean', 'api_token_days' => 'required|integer|min:1|max:365', 'audit_retention_days' => 'required|integer|min:30|max:3650']),
            'maintenance' => $request->validate(['enabled' => 'boolean', 'data_retention_days' => 'required|integer|min:30|max:36500', 'backup_frequency' => ['required', Rule::in(['manual', 'daily', 'weekly', 'monthly'])]]),
        };
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function booleanFields(string $section): array
    {
        return match ($section) {
            'violations' => ['impounding_enabled'],
            'payments' => ['online_enabled', 'refunds_enabled'],
            'notifications' => ['new_violation', 'payment_confirmation', 'release_notice', 'overdue_reminder', 'failed_payment'],
            'security' => ['two_factor'],
            'maintenance' => ['enabled'],
            default => [],
        };
    }
}
