@extends(auth()->user()->isSupervisor() ? 'layouts.supervisor-dashboard' : 'layouts.admin-dashboard')

@section('title', 'My Profile')
@section('activePage', 'profile')
@section('workspaceClass', 'profile-workspace')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
@endpush

@section('content')
    <div class="page-head profile-page-heading">
        <div><p class="eyebrow">Account settings</p><h1>My Profile</h1><p class="page-description">Manage your personal details, photo, and password.</p></div>
    </div>
    @if (session('success'))
        <div class="success-message" role="status">{{ session('success') }}</div>
    @endif

    <div class="profile-shell">
        <form id="profileForm" novalidate method="POST" enctype="multipart/form-data" action="{{ route('profile.update') }}">@csrf @method('PUT')
            <section class="profile-identity" aria-labelledby="profileName">
                <x-profile-photo-field :user="$profileUser" />
                <div class="profile-identity-copy">
                    <span class="profile-role">{{ $profileUser->isOfficer() ? 'Enforcer' : ucfirst($profileUser->role) }}</span>
                    <h2 id="profileName">{{ $profileUser->fullName }}</h2>
                    <p>{{ $profileUser->email }}</p>
                    @if ($profileUser->username)<span class="profile-username">{{ '@'.$profileUser->username }}</span>@endif
                </div>
            </section>
            <div class="profile-content-grid">
            <section class="profile-section" aria-labelledby="personalDetailsTitle">
                <header class="profile-section-heading"><h2 id="personalDetailsTitle">Personal information</h2><p>Keep your contact details up to date.</p></header>
                <div class="user-form-grid">
                <div class="form-field"><label for="firstName">First name <b>Required</b></label><input id="firstName"
                        name="firstName" autocomplete="given-name" value="{{ old('firstName', $profileUser->firstName) }}" required>
                    @error('firstName')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="middleName">Middle name <em>Optional</em></label><input id="middleName"
                        name="middleName" value="{{ old('middleName', $profileUser->middleName) }}">
                    @error('middleName')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="lastName">Last name <b>Required</b></label><input id="lastName"
                        name="lastName" autocomplete="family-name" value="{{ old('lastName', $profileUser->lastName) }}" required>
                    @error('lastName')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="nameExtension">Name extension <em>Optional</em></label><input
                        id="nameExtension" name="nameExtension"
                        value="{{ old('nameExtension', $profileUser->nameExtension) }}">
                    @error('nameExtension')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="username">Username <b>Required</b></label><input id="username"
                        name="username" value="{{ old('username', $profileUser->username) }}" required>
                    @error('username')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="address">Address <b>Required</b></label><input id="address"
                        name="address" value="{{ old('address', $profileUser->address) }}" required>
                    @error('address')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="phoneNumber">Phone number <b>Required</b></label><input id="phoneNumber"
                        name="phoneNumber" type="tel" value="{{ old('phoneNumber', $profileUser->phoneNumber) }}"
                        pattern="09[0-9]{9}" maxlength="11" required>
                    @error('phoneNumber')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="email">Email address <b>Required</b></label><input id="email"
                        name="email" autocomplete="email" type="email" value="{{ old('email', $profileUser->email) }}" required>
                    @error('email')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                </div>
            </section>
            <aside class="profile-section profile-account" aria-labelledby="accountDetailsTitle">
                <header class="profile-section-heading"><h2 id="accountDetailsTitle">Account details</h2><p>Your organization manages your role and assignment.</p></header>
                <dl>
                    <div><dt>Role</dt><dd>{{ $profileUser->isOfficer() ? 'Enforcer' : ucfirst($profileUser->role) }}</dd></div>
                    <div><dt>Member since</dt><dd>{{ $profileUser->created_at?->format('M d, Y') ?? '—' }}</dd></div>
                    @if ($profileUser->isOfficer())
                        <div><dt>Supervisor</dt><dd>{{ $profileUser->supervisor?->fullName ?? 'Unassigned' }}</dd></div>
                        <div><dt>Assigned area</dt><dd>{{ $profileUser->area ?: 'Unassigned' }}</dd></div>
                    @endif
                </dl>
                <p class="profile-account-note">Contact an administrator if your account assignment needs to change.</p>
            </aside>
            <section class="profile-section profile-security" aria-labelledby="securityTitle">
                <header class="profile-section-heading"><h2 id="securityTitle">Password &amp; security</h2><p>Leave these fields empty to keep your current password.</p></header>
                <div class="user-form-grid">
                <div class="form-field"><label for="password">New password <em>Optional</em></label><input id="password"
                        name="password" type="password" autocomplete="new-password">
                    @error('password')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="password_confirmation">Confirm password <em>Optional</em></label><input
                        id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>
            </div>
            </section>
            </div>
            <div class="user-form-actions profile-form-actions"><a class="page-button" href="{{ route('dashboard') }}">Cancel</a><button
                    type="submit">Save profile</button></div>
        </form>
    </div>
@endsection
