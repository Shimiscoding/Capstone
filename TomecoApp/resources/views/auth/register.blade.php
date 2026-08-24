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

                <div><label for="firstName" class="auth-label">First name</label><input id="firstName" name="firstName" type="text" value="{{ old('firstName') }}" autocomplete="given-name" required autofocus class="auth-input">@error('firstName')<p class="auth-error">{{ $message }}</p>@enderror</div>
                <div><label for="middleName" class="auth-label">Middle name <small>(optional)</small></label><input id="middleName" name="middleName" type="text" value="{{ old('middleName') }}" autocomplete="additional-name" class="auth-input">@error('middleName')<p class="auth-error">{{ $message }}</p>@enderror</div>
                <div><label for="lastName" class="auth-label">Last name</label><input id="lastName" name="lastName" type="text" value="{{ old('lastName') }}" autocomplete="family-name" required class="auth-input">@error('lastName')<p class="auth-error">{{ $message }}</p>@enderror</div>
                <div><label for="nameExtension" class="auth-label">Extension <small>(optional)</small></label><input id="nameExtension" name="nameExtension" type="text" value="{{ old('nameExtension') }}" placeholder="Jr., Sr., III" class="auth-input">@error('nameExtension')<p class="auth-error">{{ $message }}</p>@enderror</div>

                <div>
                    <label for="phoneNumber" class="auth-label">Phone number</label>
                    <input id="phoneNumber" name="phoneNumber" type="tel" value="{{ old('phoneNumber') }}" placeholder="09171234567" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" title="Enter exactly 11 digits starting with 09" autocomplete="tel" oninput="this.value=this.value.replace(/\D/g, '').slice(0, 11)" required class="auth-input">
                    @error('phoneNumber')
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
