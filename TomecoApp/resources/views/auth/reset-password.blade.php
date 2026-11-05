<x-auth-layout title="Reset password" page="reset-password">
    <p class="auth-page-label">Password recovery</p>
    <h1 id="auth-heading" class="auth-title">Choose a new password</h1>
    <p class="auth-description">Enter and confirm your new password to get back to your account.</p>

    <form method="POST" action="{{ route('password.update') }}" class="auth-form" data-auth-form novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-auth-field name="email" label="Email" type="email" :value="$email" autocomplete="email" placeholder="you@example.com" required autofocus maxlength="255" />
        <x-auth-field name="password" label="New password" type="password" autocomplete="new-password" placeholder="Create a new password" required minlength="{{ $passwordMinLength }}" hint="At least {{ $passwordMinLength }} characters." />
        <x-auth-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" placeholder="Repeat your new password" required />
        <p class="auth-form-status sr-only" role="status" aria-live="polite"></p>
        <button type="submit" class="auth-button" data-busy-label="Resetting password…">Reset password</button>
    </form>

    <p class="auth-note"><a href="{{ route('login') }}" class="auth-link">Back to login</a></p>
</x-auth-layout>
