<x-auth-layout title="Forgot password" page="forgot-password">
    <p class="auth-page-label">Password recovery</p>
    <h1 id="auth-heading" class="auth-title">Forgot your password?</h1>
    <p class="auth-description">Enter your registered email address and we'll send you a password reset link.</p>

    @if (session('status'))
        <p class="auth-status" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form" data-auth-form novalidate>
        @csrf
        <x-auth-field name="email" label="Email" type="email" autocomplete="email" placeholder="you@example.com" required autofocus maxlength="255" />
        <p class="auth-form-status sr-only" role="status" aria-live="polite"></p>
        <button type="submit" class="auth-button" data-busy-label="Sending reset link…">Email password reset link</button>
    </form>

    <p class="auth-note"><a href="{{ route('login') }}" class="auth-link">Back to login</a></p>
</x-auth-layout>
