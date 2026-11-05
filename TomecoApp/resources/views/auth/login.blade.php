<x-auth-layout title="Log in">
    <p class="auth-page-label">Log in</p>
    <h1 id="auth-heading" class="auth-title">Welcome back.</h1>
    <p class="auth-description">Log in to manage your daily traffic operations.</p>

    @if (session('status'))
        <p class="auth-status" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="auth-form" data-auth-form novalidate>
        @csrf
        <x-auth-field name="login" label="Username or email" autocomplete="username" placeholder="Enter your username or email" required autofocus maxlength="255" />
        <x-auth-field name="password" label="Password" type="password" autocomplete="current-password" placeholder="Enter your password" required />
        <div class="auth-login-options">
            <label class="auth-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label>
            <a href="{{ route('password.request') }}" class="auth-link auth-forgot-link">Forgot password?</a>
        </div>
        <p class="auth-form-status sr-only" role="status" aria-live="polite"></p>
        <button type="submit" class="auth-button" data-busy-label="Logging in…">Log in <span aria-hidden="true">→</span></button>
    </form>
    @if ($adminSignupEnabled)
        <p class="auth-note">Need an administrator account? <a href="{{ route('register') }}" class="auth-link">Sign up as admin</a></p>
    @endif
    <p class="auth-note auth-support-note">Having trouble logging in?<br>Contact your administrator for help.</p>
</x-auth-layout>
