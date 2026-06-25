<!DOCTYPE html>
<html lang= "en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign up</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card">
            <p class="auth-brand">TomecoApp</p>
            <h1 class="auth-title">Create your account</h1>

            <form method="POST" action="{{ route('register.store') }}" class="auth-form">
                @csrf

                <div>
                    <label for="name" class="auth-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus class="auth-input">
                    @error('name')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="auth-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="auth-input">
                    @error('email')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="auth-label">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="auth-input">
                    @error('password')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="auth-label">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="auth-input">
                </div>

                <button type="submit" class="auth-button">Sign up</button>
            </form>

            <p class="auth-note">
                Already have an account?
                <a href="{{ route('login') }}" class="auth-link">Log in</a>
            </p>
        </section>
    </main>
</body>
</html>
