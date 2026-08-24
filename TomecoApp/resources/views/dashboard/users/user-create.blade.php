@extends('layouts.dashboard')

@php $sectionLabel = match ($userSection ?? null) { 'supervisors' => 'Supervisor', 'enforcers' => 'Enforcer', 'admins' => 'Admin', default => 'User' }; $isRoleLocked = isset($fixedRole) || isset($editedUser); $lockedRole = $fixedRole ?? ($editedUser->role ?? null); @endphp
@section('title', isset($editedUser) ? 'Edit User' : 'Create '.$sectionLabel)
@section('activePage', ($userSection ?? null) ? 'users-'.$userSection : 'users')

@section('content')
  <div class="page-head users-page-head"><div><p class="eyebrow">User management</p><h1>{{ isset($editedUser) ? 'Edit user' : 'Create '.$sectionLabel }}</h1><p class="page-description">{{ isset($editedUser) ? 'Update this TOMECO account.' : 'Add a new '.strtolower($sectionLabel).' account.' }}</p></div><a class="page-button" href="{{ match ($userSection ?? null) { 'supervisors' => route('dashboard.users.supervisors'), 'enforcers' => route('dashboard.users.enforcers'), 'admins' => route('dashboard.users.admins'), default => route('dashboard.users') } }}">Back</a></div>
  <div class="create-user-card"><form method="POST" action="{{ isset($editedUser) ? route('dashboard.users.update', $editedUser) : route('dashboard.users.store') }}">
    @csrf @isset($editedUser) @method('PUT') @endisset
    @if(isset($userSection))<input type="hidden" name="user_section" value="{{ $userSection }}">@endif
    <div class="user-form-grid">
      <div class="form-section-heading"><span>1</span><div><h2>Personal information</h2><p>Enter the user's name exactly as shown on an official ID.</p></div></div>
      <div class="form-field"><label for="firstName">First name <b>Required</b></label><input id="firstName" name="firstName" type="text" value="{{ old('firstName', $editedUser->firstName ?? '') }}" placeholder="e.g., Juan" autocomplete="given-name" required autofocus>@error('firstName')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Given name only.</small>@enderror</div>
      <div class="form-field"><label for="middleName">Middle name <em>Optional</em></label><input id="middleName" name="middleName" type="text" value="{{ old('middleName', $editedUser->middleName ?? '') }}" placeholder="e.g., Santos" autocomplete="additional-name">@error('middleName')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Leave blank if the user has no middle name.</small>@enderror</div>
      <div class="form-field"><label for="lastName">Last name <b>Required</b></label><input id="lastName" name="lastName" type="text" value="{{ old('lastName', $editedUser->lastName ?? '') }}" placeholder="e.g., Dela Cruz" autocomplete="family-name" required>@error('lastName')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Family or surname.</small>@enderror</div>
      <div class="form-field"><label for="nameExtension">Name extension <em>Optional</em></label><input id="nameExtension" name="nameExtension" type="text" value="{{ old('nameExtension', $editedUser->nameExtension ?? '') }}" placeholder="e.g., Jr., Sr., III" maxlength="20">@error('nameExtension')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Do not enter professional titles such as Atty. or Engr.</small>@enderror</div>
      <div class="form-field"><label for="username">Username <b>Required</b></label><input id="username" name="username" type="text" value="{{ old('username', $editedUser->username ?? '') }}" placeholder="e.g., juan.delacruz" autocomplete="username" required>@error('username')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Letters, numbers, dashes, and underscores only.</small>@enderror</div>
      <div class="form-field"><label for="address">Address <b>Required</b></label><input id="address" name="address" type="text" value="{{ old('address', $editedUser->address ?? '') }}" placeholder="House number and street" autocomplete="street-address" required>@error('address')<small class="field-error">{{ $message }}</small>@enderror</div>
      <div class="form-field"><label for="area">Area <b>Required</b></label><input id="area" name="area" type="text" value="{{ old('area', $editedUser->area ?? '') }}" placeholder="e.g., Area 1" required>@error('area')<small class="field-error">{{ $message }}</small>@enderror</div>
      <div class="form-field"><label for="barangay">Barangay <b>Required</b></label><input id="barangay" name="barangay" type="text" value="{{ old('barangay', $editedUser->barangay ?? '') }}" placeholder="e.g., Barangay 12" required>@error('barangay')<small class="field-error">{{ $message }}</small>@enderror</div>

      @unless($isRoleLocked)<div class="form-section-heading"><span>2</span><div><h2>Account assignment</h2><p>Select a role first; applicable identification fields will be enabled automatically.</p></div></div>@endunless
      @if($isRoleLocked)
        <input id="role" name="role" type="hidden" value="{{ $fixedRole ?? $editedUser->role }}">
      @else
        <div class="form-field"><label for="role">Account role <b>Required</b></label><select id="role" name="role" required><option value="officer" @selected(old('role', $editedUser->role ?? 'officer') === 'officer')>Enforcer</option><option value="supervisor" @selected(old('role', $editedUser->role ?? '') === 'supervisor')>Supervisor</option><option value="driver" @selected(old('role', $editedUser->role ?? '') === 'driver')>Driver</option><option value="admin" @selected(old('role', $editedUser->role ?? '') === 'admin')>Administrator</option></select>@error('role')<small class="field-error">{{ $message }}</small>@else<small class="field-help" id="roleHelp">Controls dashboard permissions and required identification.</small>@enderror</div>
      @endif
      <div class="form-field" id="supervisorField"><label for="supervisor_id">Supervisor <b>Required for enforcers</b></label><select id="supervisor_id" name="supervisor_id"><option value="">Select a supervisor</option>@foreach($supervisors as $supervisor)<option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $editedUser->supervisor_id ?? '') === (string) $supervisor->id)>{{ $supervisor->fullName }}{{ $supervisor->area ? ' — '.$supervisor->area : '' }}</option>@endforeach</select>@error('supervisor_id')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Identifies the supervisor responsible for this enforcer.</small>@enderror</div>
      @if(!$isRoleLocked || $lockedRole === \App\Models\User::ROLE_DRIVER)
        <div class="form-field"><label for="plateNumber">Vehicle plate number <span id="plateHint"></span></label><input id="plateNumber" name="plateNumber" type="text" value="{{ old('plateNumber', $editedUser->plateNumber ?? '') }}" placeholder="e.g., ABC 1234">@error('plateNumber')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="form-field"><label for="driverLicense">Driver license number <em>Optional</em></label><input id="driverLicense" name="driverLicense" type="text" value="{{ old('driverLicense', $editedUser->driverLicense ?? '') }}" placeholder="e.g., N01-23-456789">@error('driverLicense')<small class="field-error">{{ $message }}</small>@enderror</div>
      @endif

      <div class="form-section-heading"><span>2</span><div><h2>Contact and security</h2><p>Provide working contact details and secure account credentials.</p></div></div>
      <div class="form-field demo-account-field"><label for="demo_account"><input id="demo_account" name="demo_account" type="checkbox" value="1" @checked(old('demo_account', isset($editedUser) && str_ends_with($editedUser->email, '@demo.tomeco.local')))><span><strong>{{ isset($editedUser) ? 'Use demo email' : 'Create as demo account' }}</strong><small class="field-help">Generates a demo email from the username and skips email verification.</small></span></label></div>
      <div class="form-field"><label for="phoneNumber">Phone number <b>Required</b></label><input id="phoneNumber" name="phoneNumber" type="tel" value="{{ old('phoneNumber', $editedUser->phoneNumber ?? '') }}" placeholder="e.g., 09171234567" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" title="Enter exactly 11 digits starting with 09" autocomplete="tel" oninput="this.value=this.value.replace(/\D/g, '').slice(0, 11)" required>@error('phoneNumber')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Enter exactly 11 digits beginning with 09.</small>@enderror</div>
      <div class="form-field"><label for="email">Email address <b id="emailRequiredHint">Required</b></label><input id="email" name="email" type="email" value="{{ old('email', $editedUser->email ?? '') }}" placeholder="e.g., juan@example.com" autocomplete="email" required>@error('email')<small class="field-error">{{ $message }}</small>@else<small class="field-help">This email is used to sign in.</small>@enderror</div>
      <div class="form-field"><label for="password">{{ isset($editedUser) ? 'New password' : 'Temporary password' }} @if(isset($editedUser))<em>Optional</em>@else<b>Required</b>@endif</label><div class="user-password-field"><input id="password" name="password" type="password" placeholder="{{ isset($editedUser) ? 'Leave blank to keep current password' : 'At least 8 characters' }}" autocomplete="new-password" @required(!isset($editedUser))><button class="user-password-toggle" type="button" data-password-target="password" aria-label="Show temporary password" aria-pressed="false">Show</button></div>@error('password')<small class="field-error">{{ $message }}</small>@else<small class="field-help">Use a strong password that is not shared with other accounts.</small>@enderror</div>
      <div class="form-field"><label for="password_confirmation">Confirm password @if(isset($editedUser))<em>Optional</em>@else<b>Required</b>@endif</label><div class="user-password-field"><input id="password_confirmation" name="password_confirmation" type="password" placeholder="Re-enter the password" autocomplete="new-password" @required(!isset($editedUser))><button class="user-password-toggle" type="button" data-password-target="password_confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button></div><small class="field-help">Must exactly match the password above.</small></div>
    </div>
    <div class="user-form-actions"><a class="page-button" href="{{ route('dashboard.users') }}">Cancel</a><button type="submit">{{ isset($editedUser) ? 'Update user' : 'Create user' }}</button></div>
  </form></div>
@endsection

@push('scripts')
<script>
  const roleInput = document.getElementById('role'); const plateInput = document.getElementById('plateNumber'); const plateHint = document.getElementById('plateHint'); const licenseInput = document.getElementById('driverLicense'); const supervisorInput = document.getElementById('supervisor_id'); const supervisorField = document.getElementById('supervisorField');
  function syncRoleFields() { const isDriver = roleInput.value === 'driver'; const isEnforcer = roleInput.value === 'officer'; if (plateInput) { plateInput.disabled = !isDriver; plateInput.required = isDriver; plateInput.placeholder = isDriver ? 'Enter the vehicle plate number' : 'Not applicable'; if (plateHint) plateHint.textContent = isDriver ? '(required)' : '(not applicable)'; } if (licenseInput) { licenseInput.disabled = !isDriver; licenseInput.placeholder = isDriver ? 'Enter the driver license number' : 'Not applicable'; } supervisorField.hidden = !isEnforcer; supervisorInput.disabled = !isEnforcer; supervisorInput.required = isEnforcer; if (!isDriver) { if (plateInput) plateInput.value = ''; if (licenseInput) licenseInput.value = ''; } }
  roleInput.addEventListener('change', syncRoleFields); syncRoleFields();
  const demoAccountInput = document.getElementById('demo_account'); const emailInput = document.getElementById('email');
  function syncDemoAccount() { if (!demoAccountInput) return; const isDemo = demoAccountInput.checked; emailInput.disabled = isDemo; emailInput.required = !isDemo; emailInput.placeholder = isDemo ? 'Generated automatically from username' : 'e.g., juan@example.com'; document.getElementById('emailRequiredHint').hidden = isDemo; }
  demoAccountInput?.addEventListener('change', syncDemoAccount); syncDemoAccount();
  document.querySelectorAll('.user-password-toggle').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordTarget);
      const isVisible = input.type === 'text';
      input.type = isVisible ? 'password' : 'text';
      button.textContent = isVisible ? 'Show' : 'Hide';
      button.setAttribute('aria-pressed', String(!isVisible));
      button.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} ${input.id === 'password_confirmation' ? 'password confirmation' : 'temporary password'}`);
      input.focus();
    });
  });
</script>
@endpush
