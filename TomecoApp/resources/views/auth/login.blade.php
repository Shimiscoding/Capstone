<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>

<body class="auth-page login-page">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand auth-brand-with-logo">
                <img src="{{ asset('images/Tomeco_icon.jpg') }}" alt="TOMECO official seal">
                <span>
                    <strong>TOMECO</strong>
                    <small>Traffic Operation Management Enforcement and Control Office Tacloban</small>
                </span>
            </div>
            <h1 class="auth-title">Log in to your account</h1>

            @if (session('status'))
                <p class="auth-status" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf

                <div>
                    <label for="login" class="auth-label">Username or email</label>
                    <input id="login" name="login" type="text" value="{{ old('login', old('email')) }}"
                        autocomplete="username" required autofocus class="auth-input">
                    @error('login')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="auth-label">Password</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="auth-input">
                        <button id="passwordToggle" type="button" class="password-toggle" aria-label="Show password"
                            aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true" hidden>
                                <path d="M3 3l18 18" />
                                <path
                                    d="M10.6 6.2A11.7 11.7 0 0 1 12 6c6.5 0 10 6 10 6a17.8 17.8 0 0 1-3 3.7M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6c1.5 0 2.8-.3 4-.8" />
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="auth-login-options">
                    <label class="auth-check">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>

                    <a href="{{ route('password.request') }}" class="auth-link auth-forgot-link">
                        Forgot your password?
                    </a>
                </div>

                <button type="submit" class="auth-button">Log in</button>
            </form>

            <p class="auth-note auth-support-note">
                Having trouble accessing your account? Contact the administrator.
            </p>
            @if ($adminSignupEnabled)
                <p class="auth-note">
                    Need an administrator account?
                    <a href="{{ route('register') }}" class="auth-link">Sign up as admin</a>
                </p>
            @endif
        </section>
    </main>
    <script>
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) window.location.reload();
        });

        const password = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');
        passwordToggle.addEventListener('click', () => {
            const isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            passwordToggle.querySelector('.eye-open').hidden = !isVisible;
            passwordToggle.querySelector('.eye-closed').hidden = isVisible;
            passwordToggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            passwordToggle.setAttribute('aria-pressed', String(!isVisible));
            password.focus();
        });
    </script>
</body>

</html>
