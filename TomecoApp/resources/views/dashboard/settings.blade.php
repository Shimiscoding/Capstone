@extends('layouts.admin-dashboard')
@section('title', 'Settings')
@section('activePage', 'settings')

@section('content')
    @php
        $tabs = [
            'general' => 'General',
            'notifications' => 'Notifications',
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
            <p class="page-description">Manage general preferences, notifications, and security.</p>
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
                                <option value="Asia/Manila" @selected($get('timezone') === 'Asia/Manila')>Asia/Manila</option>
                                <option value="UTC" @selected($get('timezone') === 'UTC')>UTC</option>
                            </select>
                        </label>
                        <label>
                            <span>Date format</span>
                            <select name="date_format">
                                <option value="M j, Y" @selected($get('date_format') === 'M j, Y')>Jul 22, 2026</option>
                                <option value="Y-m-d" @selected($get('date_format') === 'Y-m-d')>2026-07-22</option>
                                <option value="d/m/Y" @selected($get('date_format') === 'd/m/Y')>22/07/2026</option>
                            </select>
                        </label>
                        <label>
                            <span>Violation prefix</span>
                            <input name="violation_prefix" value="{{ $get('violation_prefix') }}" required>
                        </label>
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
