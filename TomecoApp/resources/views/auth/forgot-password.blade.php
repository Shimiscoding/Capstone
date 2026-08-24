<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>

<body class="auth-page login-page">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand auth-brand-with-logo">
                <img src="{{ asset('images/Tomeco_icon.jpg') }}" alt="TOMECO official seal">
                <span><strong>TOMECO</strong><small>Traffic Operation Management Enforcement and Control Office
                        Tacloban</small></span>
            </div>
            <h1 class="auth-title">Forgot your password?</h1>

            @if (session('status'))
                <p class="auth-status" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                @csrf
                <div>
                    <label for="email" class="auth-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email"
                        required autofocus class="auth-input">
                    @error('email')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="auth-button">Email password reset link</button>
            </form>

            <p class="auth-note"><a href="{{ route('login') }}" class="auth-link">Back to login</a></p>
        </section>
    </main>
</body>

</html>
