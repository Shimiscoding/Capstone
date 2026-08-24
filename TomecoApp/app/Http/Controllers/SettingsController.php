<?php

namespace App\Http\Controllers;

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
        $section = $request->validate(['section' => ['required', Rule::in(['general', 'violations', 'permissions', 'notifications', 'data', 'security', 'maintenance'])]])['section'];
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
        $payload = ['created_at' => now()->toIso8601String(), 'settings' => $settings->all(), 'users' => User::all(), 'violations' => Violation::all()];

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
            'general' => $request->validate(['organization_name' => 'required|string|max:100', 'address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'timezone' => 'required|timezone', 'date_format' => 'required|string|max:30', 'violation_prefix' => 'required|string|max:10']),
            'violations' => $request->validate(['late_fee' => 'required|numeric|min:0', 'catalog_text' => 'required|string']),
            'permissions' => $request->validate(['admin' => 'array', 'officer' => 'array']),
            'notifications' => $request->validate(['new_violation' => 'boolean', 'overdue_reminder' => 'boolean', 'recipient_email' => 'nullable|email']),
            'data' => $request->validate(['max_upload_mb' => 'required|integer|min:1|max:20', 'duplicate_behavior' => ['required', Rule::in(['update', 'skip'])], 'default_format' => ['required', Rule::in(['xlsx', 'xls', 'csv'])], 'history_days' => 'required|integer|min:1|max:3650']),
            'security' => $request->validate(['password_min_length' => 'required|integer|min:8|max:64', 'session_timeout' => 'required|integer|min:5|max:1440', 'login_attempts' => 'required|integer|min:3|max:20', 'two_factor' => 'boolean', 'admin_signup_enabled' => 'boolean', 'api_token_days' => 'required|integer|min:1|max:365', 'audit_retention_days' => 'required|integer|min:30|max:3650']),
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
            'notifications' => ['new_violation', 'overdue_reminder'],
            'security' => ['two_factor', 'admin_signup_enabled'],
            'maintenance' => ['enabled'],
            default => [],
        };
    }
}
