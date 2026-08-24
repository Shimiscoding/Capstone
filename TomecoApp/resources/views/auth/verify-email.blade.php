<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify email</title>
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
            <h1 class="auth-title">Verify your email</h1>
            <p class="auth-description">Enter the six-digit code sent to <strong>{{ auth()->user()->email }}</strong>.
                It expires in 10 minutes.</p>

            @if (session('status'))
                <p class="auth-status" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}" class="auth-form">
                @csrf
                <div>
                    <label for="otp" class="auth-label">Verification code</label>
                    <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}"
                        maxlength="6" autocomplete="one-time-code" required autofocus
                        class="auth-input auth-otp-input">
                    @error('otp')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="auth-button">Verify email</button>
            </form>

            <form method="POST" action="{{ route('verification.resend') }}" class="auth-note">
                @csrf
                <button type="submit" class="auth-text-button">Resend code</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="auth-note">
                @csrf
                <button type="submit" class="auth-text-button">Log out</button>
            </form>
        </section>
    </main>
</body>

</html>
