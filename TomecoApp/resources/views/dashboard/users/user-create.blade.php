@extends('layouts.admin-dashboard')

@php
    $sectionLabel = match ($userSection ?? null) {
        'supervisors' => 'Supervisor',
        'enforcers' => 'Enforcer',
        'admins' => 'Admin',
        default => 'User',
    };
    $isRoleLocked = isset($fixedRole) || isset($editedUser);
    $lockedRole = $fixedRole ?? ($editedUser->role ?? null);
    $returnRoute = ($returnTo ?? null) === 'detail' && isset($editedUser)
        ? match ($editedUser->role) {
            \App\Models\User::ROLE_SUPERVISOR => route('dashboard.users.supervisors.show', $editedUser),
            \App\Models\User::ROLE_OFFICER => route('dashboard.users.enforcers.show', $editedUser),
            \App\Models\User::ROLE_ADMIN => route('dashboard.users.admins.show', $editedUser),
            default => route('dashboard.users.supervisors'),
        }
        : match ($userSection ?? null) {
            'supervisors' => route('dashboard.users.supervisors'),
            'enforcers' => route('dashboard.users.enforcers'),
            'admins' => route('dashboard.users.admins'),
            default => route('dashboard.users.supervisors'),
        };
@endphp
@section('title', isset($editedUser) ? 'Edit User' : 'Create ' . $sectionLabel)
@section('activePage', $userSection ?? null ? 'users-' . $userSection : 'users')

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">User management</p>
            <h1>{{ isset($editedUser) ? 'Edit user' : 'Create ' . $sectionLabel }}</h1>
            <p class="page-description">
                {{ isset($editedUser) ? 'Update this TOMECO account.' : 'Add a new ' . strtolower($sectionLabel) . ' account.' }}
            </p>
        </div><a class="page-button" href="{{ $returnRoute }}">Back</a>
    </div>
    <div class="create-user-card">
        <form method="POST"
            action="{{ isset($editedUser) ? route('dashboard.users.update', $editedUser) : route('dashboard.users.store') }}">
            @csrf @isset($editedUser)
                @method('PUT')
            @endisset
            @if (isset($userSection))
                <input type="hidden" name="user_section" value="{{ $userSection }}">
            @endif
            @if (($returnTo ?? null) === 'detail')
                <input type="hidden" name="return_to" value="detail">
            @endif
            <div class="user-form-grid">
                <div class="form-section-heading"><span>1</span>
                    <div>
                        <h2>Personal information</h2>
                        <p>Enter the user's name exactly as shown on an official ID.</p>
                    </div>
                </div>
                <div class="form-field"><label for="firstName">First name <b>Required</b></label><input id="firstName"
                        name="firstName" type="text" value="{{ old('firstName', $editedUser->firstName ?? '') }}"
                        placeholder="e.g., Juan" autocomplete="given-name" required autofocus>
                    @error('firstName')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Given name
                            only.</small>
                    @enderror
                </div>
                <div class="form-field"><label for="middleName">Middle name <em>Optional</em></label><input id="middleName"
                        name="middleName" type="text" value="{{ old('middleName', $editedUser->middleName ?? '') }}"
                        placeholder="e.g., Santos" autocomplete="additional-name">
                    @error('middleName')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Leave blank if the
                            user has no middle name.</small>
                    @enderror
                </div>
                <div class="form-field"><label for="lastName">Last name <b>Required</b></label><input id="lastName"
                        name="lastName" type="text" value="{{ old('lastName', $editedUser->lastName ?? '') }}"
                        placeholder="e.g., Dela Cruz" autocomplete="family-name" required>
                    @error('lastName')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Family or
                            surname.</small>
                    @enderror
                </div>
                <div class="form-field"><label for="nameExtension">Name extension <em>Optional</em></label><input
                        id="nameExtension" name="nameExtension" type="text"
                        value="{{ old('nameExtension', $editedUser->nameExtension ?? '') }}"
                        placeholder="e.g., Jr., Sr., III" maxlength="20">
                    @error('nameExtension')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Do not enter
                            professional titles such as Atty. or Engr.</small>
                    @enderror
                </div>
                <div class="form-field"><label for="username">Username <b>Required</b></label><input id="username"
                        name="username" type="text" value="{{ old('username', $editedUser->username ?? '') }}"
                        placeholder="e.g., juan.delacruz" autocomplete="username" required>
                    @error('username')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Letters, numbers,
                            dashes, and underscores only.</small>
                    @enderror
                </div>
                <div class="form-field"><label for="address">Address <b>Required</b></label><input id="address"
                        name="address" type="text" value="{{ old('address', $editedUser->address ?? '') }}"
                        placeholder="House number and street" autocomplete="street-address" required>
                    @error('address')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-field"><label for="area">Area <em>Optional</em></label><input id="area"
                        name="area" type="text" value="{{ old('area', $editedUser->area ?? '') }}"
                        placeholder="e.g., Area 1">
                    @error('area')
                        <small class="field-error">{{ $message }}</small>
                    @enderror
                </div>
                @unless ($isRoleLocked)
                    <div class="form-section-heading"><span>2</span>
                        <div>
                            <h2>Account assignment</h2>
                            <p>Select a role first; applicable identification fields will be enabled automatically.</p>
                        </div>
                    </div>
                @endunless
                @if ($isRoleLocked)
                    <input id="role" name="role" type="hidden" value="{{ $fixedRole ?? $editedUser->role }}">
                @else
                    <div class="form-field"><label for="role">Account role <b>Required</b></label><select id="role"
                            name="role" required>
                            <option value="officer" @selected(old('role', $editedUser->role ?? 'officer') === 'officer')>Enforcer</option>
                            <option value="supervisor" @selected(old('role', $editedUser->role ?? '') === 'supervisor')>Supervisor</option>
                            <option value="admin" @selected(old('role', $editedUser->role ?? '') === 'admin')>Administrator</option>
                        </select>
                        @error('role')
                        <small class="field-error">{{ $message }}</small>@else<small class="field-help"
                                id="roleHelp">Controls dashboard permissions and required identification.</small>
                        @enderror
                    </div>
                @endif
                <div class="form-field" id="supervisorField"><label for="supervisor_id">Supervisor <b>Optional</b></label><select id="supervisor_id" name="supervisor_id">
                        <option value="">No supervisor (unassigned)</option>
                        @foreach ($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $editedUser->supervisor_id ?? '') === (string) $supervisor->id)>
                                {{ $supervisor->fullName }}{{ $supervisor->area ? ' — ' . $supervisor->area : '' }}</option>
                        @endforeach
                    </select>
                    @error('supervisor_id')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Optionally assign a
                            supervisor responsible for this enforcer.</small>
                    @enderror
                </div>
                <div class="form-section-heading"><span>2</span>
                    <div>
                        <h2>Contact and security</h2>
                        <p>Provide working contact details and secure account credentials.</p>
                    </div>
                </div>
                <div class="form-field"><label for="phoneNumber">Phone number <b>Required</b></label><input
                        id="phoneNumber" name="phoneNumber" type="tel"
                        value="{{ old('phoneNumber', $editedUser->phoneNumber ?? '') }}" placeholder="e.g., 09171234567"
                        inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11"
                        title="Enter exactly 11 digits starting with 09" autocomplete="tel"
                        oninput="this.value=this.value.replace(/\D/g, '').slice(0, 11)" required>
                    @error('phoneNumber')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Enter exactly 11
                            digits beginning with 09.</small>
                    @enderror
                </div>
                <div class="form-field"><label for="email">Email address <b>Required</b></label><input id="email" name="email"
                        type="email" value="{{ old('email', $editedUser->email ?? '') }}"
                        placeholder="e.g., juan@example.com" autocomplete="email" required>
                    @error('email')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">This email is
                            used to sign in.</small>
                    @enderror
                </div>
                <div class="form-field"><label
                        for="password">{{ isset($editedUser) ? 'New password' : 'Temporary password' }} @if (isset($editedUser))
                        <em>Optional</em>@else<b>Required</b>
                        @endif
                    </label>
                    <div class="user-password-field"><input id="password" name="password" type="password"
                            placeholder="{{ isset($editedUser) ? 'Leave blank to keep current password' : 'At least 8 characters' }}"
                            autocomplete="new-password" @required(!isset($editedUser))><button class="user-password-toggle"
                            type="button" data-password-target="password" aria-label="Show temporary password"
                            aria-pressed="false">Show</button></div>
                    @error('password')
                    <small class="field-error">{{ $message }}</small>@else<small class="field-help">Use a strong
                            password that is not shared with other accounts.</small>
                    @enderror
                </div>
                @isset($editedUser)
                    <div class="form-field"><label for="account_status">Account status <b>Required</b></label><select id="account_status" name="account_status" required>
                        @foreach (\App\Models\User::ACCOUNT_STATUSES as $accountStatus)
                            <option value="{{ $accountStatus }}" @selected(old('account_status', $editedUser->effectiveAccountStatus()) === $accountStatus)>{{ ucfirst($accountStatus) }}</option>
                        @endforeach
                    </select>
                    @error('account_status')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Inactive, suspended, and banned accounts cannot sign in.</small>@enderror</div>
                @endisset
                <div class="form-field"><label for="password_confirmation">Confirm password @if (isset($editedUser))
                        <em>Optional</em>@else<b>Required</b>
                        @endif
                    </label>
                    <div class="user-password-field"><input id="password_confirmation" name="password_confirmation"
                            type="password" placeholder="Re-enter the password" autocomplete="new-password"
                            @required(!isset($editedUser))><button class="user-password-toggle" type="button"
                            data-password-target="password_confirmation" aria-label="Show password confirmation"
                            aria-pressed="false">Show</button></div><small class="field-help">Must exactly match the
                        password above.</small>
                </div>
            </div>
            <div class="user-form-actions"><a class="page-button"
                    href="{{ $returnRoute }}">Cancel</a><button
                    type="submit">{{ isset($editedUser) ? 'Update user' : 'Create user' }}</button></div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const roleInput = document.getElementById('role');
        const supervisorInput = document.getElementById('supervisor_id');
        const supervisorField = document.getElementById('supervisorField');

        function syncRoleFields() {
            const isEnforcer = roleInput.value === 'officer';
            supervisorField.hidden = !isEnforcer;
            supervisorInput.disabled = !isEnforcer;
            supervisorInput.required = false;
        }
        roleInput.addEventListener('change', syncRoleFields);
        syncRoleFields();
        document.querySelectorAll('.user-password-toggle').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordTarget);
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                button.textContent = isVisible ? 'Show' : 'Hide';
                button.setAttribute('aria-pressed', String(!isVisible));
                button.setAttribute('aria-label',
                    `${isVisible ? 'Show' : 'Hide'} ${input.id === 'password_confirmation' ? 'password confirmation' : 'temporary password'}`
                    );
                input.focus();
            });
        });
    </script>
@endpush
