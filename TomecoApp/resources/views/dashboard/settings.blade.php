@extends('layouts.admin-dashboard')
@section('title', 'Settings')
@section('activePage', 'settings')

@section('content')
    @php
        $tabs = [
            'general' => 'General',
            'violations' => 'Violations & Fines',
            'permissions' => 'Roles & Permissions',
            'notifications' => 'Notifications',
            'data' => 'Import & Export',
            'security' => 'Security',
        ];
        $activeTab = array_key_exists($activeTab, $tabs) ? $activeTab : 'general';
        $get = fn($key, $fallback = '') => old($key, $settings[$activeTab . '.' . $key] ?? $fallback);
        $checked = fn($key) => old($key, $settings[$activeTab . '.' . $key] ?? false);
    @endphp
    @if (session('success'))
        <div class="flash-alert is-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="flash-alert is-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="page-head">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>System Settings</h1>
            <p class="page-description">Configure TOMECO operations, access, and data policies.</p>
        </div>
    </div>

    <div class="settings-shell">
        <nav class="settings-tabs" aria-label="Settings sections">
            @foreach ($tabs as $key => $label)
                <a class="{{ $activeTab === $key ? 'is-active' : '' }}"
                    href="{{ route('dashboard.settings', ['tab' => $key]) }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <section class="settings-card">
            <div class="settings-heading">
                <h2>{{ $tabs[$activeTab] }}</h2>
                <p>Changes are stored securely and apply across the application.</p>
            </div>

            <form method="POST" action="{{ route('dashboard.settings.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="{{ $activeTab }}">
                <div class="settings-grid">
                    @if ($activeTab === 'general')
                        <label>
                            <span>Organization name</span>
                            <input name="organization_name" value="{{ $get('organization_name') }}" required>
                        </label>
                        <label>
                            <span>Contact email</span>
                            <input type="email" name="email" value="{{ $get('email') }}">
                        </label>
                        <label class="wide">
                            <span>Office address</span>
                            <input name="address" value="{{ $get('address') }}">
                        </label>
                        <label>
                            <span>Phone number</span>
                            <input name="phone" value="{{ $get('phone') }}">
                        </label>
                        <label>
                            <span>Timezone</span>
                            <select name="timezone">
                                <option>Asia/Manila</option>
                                <option>UTC</option>
                            </select>
                        </label>
                        <label>
                            <span>Date format</span>
                            <select name="date_format">
                                <option value="M j, Y">Jul 22, 2026</option>
                                <option value="Y-m-d">2026-07-22</option>
                                <option value="d/m/Y">22/07/2026</option>
                            </select>
                        </label>
                        <label>
                            <span>Violation prefix</span>
                            <input name="violation_prefix" value="{{ $get('violation_prefix') }}" required>
                        </label>
                    @elseif ($activeTab === 'violations')
                        <label>
                            <span>Late fee (PHP)</span>
                            <input type="number" step="0.01" name="late_fee" value="{{ $get('late_fee') }}"
                                min="0" required>
                        </label>
                        <label class="wide">
                            <span>Violation catalog <small>One per line: Name | Fine</small></span>
                            <textarea name="catalog_text" rows="7" required>{{ collect($settings['violations.catalog'])->map(fn($violation) => $violation['name'] . ' | ' . $violation['fine'])->join("\n") }}</textarea>
                        </label>
                    @elseif ($activeTab === 'permissions')
                        @php
                            $permissions = [
                                'manage_users' => 'Manage users',
                                'manage_settings' => 'Manage settings',
                                'record_violations' => 'Record violations',
                                'import' => 'Import files',
                                'export' => 'Export reports',
                            ];
                        @endphp
                        @foreach (['admin' => 'Administrator', 'supervisor' => 'Supervisor'] as $role => $roleLabel)
                            <fieldset class="permission-group">
                                <legend>{{ $roleLabel }}</legend>
                                @foreach ($permissions as $permission => $label)
                                    <label class="toggle-field">
                                        <input type="checkbox" name="{{ $role }}[]" value="{{ $permission }}"
                                            @checked(in_array($permission, $settings['permissions.' . $role])) @disabled($role === 'admin' && $permission === 'manage_settings')>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                                @if ($role === 'admin')
                                    <input type="hidden" name="admin[]" value="manage_settings">
                                @endif
                            </fieldset>
                        @endforeach
                    @elseif ($activeTab === 'notifications')
                        @foreach (['new_violation' => 'New violation alerts', 'overdue_reminder' => 'Overdue fine reminders'] as $key => $label)
                            <label class="toggle-field">
                                <input type="checkbox" name="{{ $key }}" value="1"
                                    @checked($checked($key))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        <label class="wide">
                            <span>Administrative recipient email</span>
                            <input type="email" name="recipient_email" value="{{ $get('recipient_email') }}">
                        </label>
                    @elseif ($activeTab === 'data')
                        @foreach (['max_upload_mb' => 'Maximum upload size (MB)', 'history_days' => 'Import history retention (days)'] as $field => $label)
                            <label>
                                <span>{{ $label }}</span>
                                <input type="number" name="{{ $field }}" value="{{ $get($field) }}"
                                    min="1">
                            </label>
                        @endforeach
                        <label>
                            <span>Duplicate references</span>
                            <select name="duplicate_behavior">
                                <option value="update" @selected($get('duplicate_behavior') === 'update')>Update existing record</option>
                                <option value="skip" @selected($get('duplicate_behavior') === 'skip')>Skip duplicate</option>
                            </select>
                        </label>
                        <label>
                            <span>Default export format</span>
                            <select name="default_format">
                                @foreach (['xlsx', 'xls', 'csv'] as $format)
                                    <option @selected($get('default_format') === $format)>{{ $format }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="settings-note wide">Spreadsheet exports use the format selected above.</div>
                    @elseif ($activeTab === 'security')
                        @foreach (['password_min_length' => 'Minimum password length', 'session_timeout' => 'Session timeout (minutes)', 'login_attempts' => 'Maximum login attempts', 'api_token_days' => 'API token expiry (days)', 'audit_retention_days' => 'Audit retention (days)'] as $field => $label)
                            <label>
                                <span>{{ $label }}</span>
                                <input type="number" name="{{ $field }}" value="{{ $get($field) }}">
                            </label>
                        @endforeach
                        <label class="toggle-field">
                            <input type="checkbox" name="two_factor" value="1" @checked($checked('two_factor'))>
                            <span>Require two-factor authentication</span>
                        </label>
                        <label class="toggle-field wide">
                            <input type="checkbox" name="admin_signup_enabled" value="1"
                                @checked($checked('admin_signup_enabled'))>
                            <span>Allow admin signup <small>Shows “Sign up as admin” on the login page. Turn this off after creating the required administrator account.</small></span>
                        </label>
                    @endif
                </div>
                <div class="settings-actions">
                    <button type="submit">Save {{ $tabs[$activeTab] }}</button>
                </div>
            </form>

        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-lines]').forEach((area) => {
            const sync = () => {
                const box = document.querySelector(`[data-line-inputs="${area.dataset.lines}"]`);
                box.innerHTML = '';

                area.value
                    .split(/\r?\n/)
                    .map((value) => value.trim())
                    .filter(Boolean)
                    .forEach((value) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `${area.dataset.lines}[]`;
                        input.value = value;
                        box.appendChild(input);
                    });
            };

            area.addEventListener('input', sync);
            sync();
        });
    </script>
@endpush
