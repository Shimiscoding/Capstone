@extends('layouts.dashboard')

@section('title', isset($editedUser) ? 'Edit User' : 'Create User')
@section('activePage', 'users')

@section('content')
  <div class="page-head users-page-head"><div><p class="eyebrow">User management</p><h1>{{ isset($editedUser) ? 'Edit user' : 'Create user' }}</h1><p class="page-description">{{ isset($editedUser) ? 'Update this TOMECO account and its assigned role.' : 'Add a TOMECO account and assign the appropriate role.' }}</p></div><a class="page-button" href="{{ route('dashboard.users') }}">Back to users</a></div>
  <div class="create-user-card"><form method="POST" action="{{ isset($editedUser) ? route('dashboard.users.update', $editedUser) : route('dashboard.users.store') }}">
    @csrf @isset($editedUser) @method('PUT') @endisset
    <div class="user-form-grid">
      <div><label for="fullName">Full name</label><input id="fullName" name="fullName" type="text" value="{{ old('fullName', $editedUser->fullName ?? '') }}" autocomplete="name" required autofocus>@error('fullName')<small>{{ $message }}</small>@enderror</div>
      <div><label for="badgeNumber">Badge number <span id="badgeHint"></span></label><input id="badgeNumber" name="badgeNumber" type="text" value="{{ old('badgeNumber', (($editedUser->role ?? null) === \App\Models\User::ROLE_DRIVER ? '' : ($editedUser->badgeNumber ?? ''))) }}">@error('badgeNumber')<small>{{ $message }}</small>@enderror</div>
      <div><label for="plateNumber">Plate number <span id="plateHint"></span></label><input id="plateNumber" name="plateNumber" type="text" value="{{ old('plateNumber', $editedUser->plateNumber ?? '') }}">@error('plateNumber')<small>{{ $message }}</small>@enderror</div>
      <div><label for="phoneNumber">Phone number</label><input id="phoneNumber" name="phoneNumber" type="tel" value="{{ old('phoneNumber', $editedUser->phoneNumber ?? '') }}" autocomplete="tel" required>@error('phoneNumber')<small>{{ $message }}</small>@enderror</div>
      <div><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', $editedUser->email ?? '') }}" autocomplete="email" required>@error('email')<small>{{ $message }}</small>@enderror</div>
      <div><label for="role">Role</label><select id="role" name="role" required><option value="officer" @selected(old('role', $editedUser->role ?? 'officer') === 'officer')>Officer</option><option value="driver" @selected(old('role', $editedUser->role ?? '') === 'driver')>Driver</option><option value="admin" @selected(old('role', $editedUser->role ?? '') === 'admin')>Admin</option></select>@error('role')<small>{{ $message }}</small>@enderror</div>
      <div><label for="password">{{ isset($editedUser) ? 'New password (optional)' : 'Temporary password' }}</label><input id="password" name="password" type="password" autocomplete="new-password" @required(!isset($editedUser))>@error('password')<small>{{ $message }}</small>@enderror</div>
      <div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!isset($editedUser))></div>
    </div>
    <div class="user-form-actions"><a class="page-button" href="{{ route('dashboard.users') }}">Cancel</a><button type="submit">{{ isset($editedUser) ? 'Update user' : 'Create user' }}</button></div>
  </form></div>
@endsection

@push('scripts')
<script>
  const roleInput = document.getElementById('role'); const badgeInput = document.getElementById('badgeNumber'); const badgeHint = document.getElementById('badgeHint'); const plateInput = document.getElementById('plateNumber'); const plateHint = document.getElementById('plateHint');
  function syncRoleFields() { const isDriver = roleInput.value === 'driver'; badgeInput.disabled = isDriver; badgeInput.required = !isDriver; badgeInput.placeholder = isDriver ? 'Not applicable for drivers' : ''; badgeHint.textContent = isDriver ? '(not applicable)' : '(required)'; if (isDriver) badgeInput.value = ''; plateInput.disabled = !isDriver; plateInput.required = isDriver; plateInput.placeholder = isDriver ? 'Enter the vehicle plate number' : 'Not applicable'; plateHint.textContent = isDriver ? '(required)' : '(not applicable)'; if (!isDriver) plateInput.value = ''; }
  roleInput.addEventListener('change', syncRoleFields); syncRoleFields();
</script>
@endpush
