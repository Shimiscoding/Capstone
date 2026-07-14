<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card">
            <p class="auth-brand">TomecoApp</p>
            <h1 class="auth-title">Log in to your account</h1>

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf

                <div>
                    <label for="email" class="auth-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="auth-input">
                    @error('email')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="auth-label">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="auth-input">
                    @error('password')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="auth-check">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>

                <button type="submit" class="auth-button">Log in</button>
            </form>

            <p class="auth-note">
                No account yet?
                <a href="{{ route('register') }}" class="auth-link">Create one</a>
            </p>
        </section>
    </main>
</body>
</html>
