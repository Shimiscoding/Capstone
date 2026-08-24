<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>
<body class="auth-page login-page">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand auth-brand-with-logo">
                <img src="{{ asset('images/Tomeco_icon.jpg') }}" alt="TOMECO official seal">
                <span><strong>TOMECO</strong><small>Traffic Operation Management Enforcement and Control Office Tacloban</small></span>
            </div>
            <h1 class="auth-title">Choose a new password</h1>

            <form method="POST" action="{{ route('password.update') }}" class="auth-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label for="email" class="auth-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus class="auth-input">
                    @error('email')<p class="auth-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="auth-label">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="auth-input">
                    @error('password')<p class="auth-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="auth-label">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="auth-input">
                </div>
                <button type="submit" class="auth-button">Reset password</button>
            </form>
        </section>
    </main>
</body>
</html>
