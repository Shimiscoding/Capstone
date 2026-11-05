<x-auth-layout title="Admin sign up" page="register">
    <p class="auth-page-label">Sign up</p>
    <h1 id="auth-heading" class="auth-title">Create your account.</h1>
    <p class="auth-description">Get started with an administrator account for TOMECO.</p>

    <form method="POST" action="{{ route('register.store') }}" class="auth-form" data-auth-form novalidate>
        @csrf
        <div class="auth-fields-grid">
            <x-auth-field name="firstName" label="First name" autocomplete="given-name" placeholder="First name" required autofocus maxlength="100" />
            <x-auth-field name="middleName" label="Middle name" autocomplete="additional-name" placeholder="Middle name" optional maxlength="100" />
            <x-auth-field name="lastName" label="Last name" autocomplete="family-name" placeholder="Last name" required maxlength="100" />
            <x-auth-field name="nameExtension" label="Extension" placeholder="Jr., Sr., III" optional maxlength="20" />
            <x-auth-field name="email" label="Email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="255" />
            <x-auth-field name="phoneNumber" label="Phone number" type="tel" autocomplete="tel" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" required hint="11 digits, starting with 09." />
            <x-auth-field name="password" label="Password" type="password" autocomplete="new-password" placeholder="Create a password" required minlength="{{ $passwordMinLength }}" hint="At least {{ $passwordMinLength }} characters." />
            <x-auth-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" placeholder="Repeat your password" required />
        </div>
        <p class="auth-form-status sr-only" role="status" aria-live="polite"></p>
        <button type="submit" class="auth-button" data-busy-label="Creating account…">Create admin account <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-note">Already have an account? <a href="{{ route('login') }}" class="auth-link">Log in</a></p>
</x-auth-layout>
