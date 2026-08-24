@extends('layouts.dashboard')

@section('title', 'My Profile')
@section('activePage', 'profile')

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">Account</p>
            <h1>My Profile</h1>
            <p class="page-description">Update your personal information and account credentials.</p>
        </div>
    </div>
    @if (session('success'))
        <div class="success-message" role="status">{{ session('success') }}</div>
    @endif

    <div class="create-user-card">
        <form method="POST" action="{{ route('profile.update') }}">@csrf @method('PUT')
            <div class="user-form-grid">
                <div class="form-section-heading"><span>1</span>
                    <div>
                        <h2>Personal information</h2>
                        <p>Your role and assignment can only be changed by an administrator.</p>
                    </div>
                </div>
                <div class="form-field"><label>Role</label><input
                        value="{{ $profileUser->isOfficer() ? 'Enforcer' : ucfirst($profileUser->role) }}" disabled></div>
                @if ($profileUser->isOfficer())
                    <div class="form-field"><label>Supervisor</label><input
                            value="{{ $profileUser->supervisor?->fullName ?? 'Unassigned' }}" disabled></div>
                @endif
                <div class="form-field"><label for="firstName">First name <b>Required</b></label><input id="firstName"
                        name="firstName" value="{{ old('firstName', $profileUser->firstName) }}" required>
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
                        name="lastName" value="{{ old('lastName', $profileUser->lastName) }}" required>
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
                <div class="form-field"><label for="area">Area <b>Required</b></label><input id="area"
                        name="area" value="{{ old('area', $profileUser->area) }}" required>
                    @error('area')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="barangay">Barangay <b>Required</b></label><input id="barangay"
                        name="barangay" value="{{ old('barangay', $profileUser->barangay) }}" required>
                    @error('barangay')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-section-heading"><span>2</span>
                    <div>
                        <h2>Contact and security</h2>
                        <p>Changing your email requires verification of the new address.</p>
                    </div>
                </div>
                <div class="form-field"><label for="phoneNumber">Phone number <b>Required</b></label><input id="phoneNumber"
                        name="phoneNumber" type="tel" value="{{ old('phoneNumber', $profileUser->phoneNumber) }}"
                        pattern="09[0-9]{9}" maxlength="11" required>
                    @error('phoneNumber')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="email">Email address <b>Required</b></label><input id="email"
                        name="email" type="email" value="{{ old('email', $profileUser->email) }}" required>
                    @error('email')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
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
            <div class="user-form-actions"><a class="page-button" href="{{ route('dashboard') }}">Cancel</a><button
                    type="submit">Save profile</button></div>
        </form>
    </div>
@endsection
