@props(['title', 'page' => 'login'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | TOMECO</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}">
    <script src="{{ asset('js/auth.js') }}" defer></script>
</head>
<body class="auth-page login-page auth-split-page auth-{{ $page }}">
    <x-page-loader />
    <main class="auth-split-shell">
        <aside class="auth-showcase" aria-label="About TOMECO">
            <div class="auth-showcase-copy">
                <p class="auth-eyebrow">Tacloban City · Traffic operations</p>
                <h2>{{ $page === 'register' ? 'Better coordination.' : 'Safer streets.' }}<br>
                    {{ $page === 'register' ? 'One connected team.' : 'Smarter operations.' }}</h2>
                <p class="auth-showcase-description">{{ $page === 'register'
                    ? 'Bring your people, records, and daily traffic operations together in one place.'
                    : 'Your team, traffic records, and daily operations in one place.' }}</p>
            </div>
            <div class="auth-showcase-footer">
                <span>Traffic Operation Management<br>Enforcement and Control Office</span>
                <span>Tacloban City<br>Philippines</span>
            </div>
        </aside>
        <section class="auth-form-panel" aria-labelledby="auth-heading">
            <div class="auth-panel-inner">
                <div class="auth-brand auth-brand-with-logo">
                    <img src="{{ asset('images/Tomeco_icon.jpg') }}" width="48" height="48" alt="TOMECO official seal">
                    <span><strong>TOMECO</strong><small>Traffic Operations · Tacloban City</small></span>
                </div>
                <div class="auth-panel-content">{{ $slot }}</div>
                <p class="auth-panel-footer">Traffic Operation Management Enforcement and Control Office Tacloban</p>
            </div>
        </section>
    </main>
</body>
</html>
