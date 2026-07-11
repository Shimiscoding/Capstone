<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#991b1b">
    <title>My Account | TOMECO</title>
    <link rel="stylesheet" href="{{ asset('css/mobile-account.css') }}?v={{ filemtime(public_path('css/mobile-account.css')) }}">
</head>
<body>
    <main class="account-page">
        <header class="account-hero">
            <div class="hero-topbar">
                <a class="back-button" href="{{ route('mobile.home') }}" aria-label="Back to home">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <h1>My Account</h1>
                <span class="topbar-space" aria-hidden="true"></span>
            </div>

            <div class="profile-summary">
                <img src="{{ asset('pwa/icons/android/launchericon-192x192.png') }}" alt="TOMECO account">
                <h2>{{ Auth::user()->name }}</h2>
                <p>Traffic Enforcement Officer</p>
            </div>
        </header>

        <section class="account-content">
            <h2>Personal Information</h2>
            <div class="details-card">
                <div class="detail-row">
                    <span class="detail-icon">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.7"/><path d="M5 21a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>
                    <div><span>Full name</span><strong>{{ Auth::user()->name }}</strong></div>
                </div>
                <div class="detail-row">
                    <span class="detail-icon">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18v13H3zM3 7l9 7 9-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div><span>Email address</span><strong>{{ Auth::user()->email }}</strong></div>
                </div>
                @if (Auth::user()->mobile)
                    <div class="detail-row">
                        <span class="detail-icon">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10v18H7zM10 18h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div><span>Mobile number</span><strong>{{ Auth::user()->mobile }}</strong></div>
                    </div>
                @endif
            </div>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="mobile.login">
                <button class="logout-button" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Sign out
                </button>
            </form>
        </section>
    </main>

    <footer class="mobile-footer">
        <nav class="footer-nav" aria-label="Mobile navigation">
            <a class="footer-link" href="{{ route('mobile.home') }}"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3.5 10.5 8.5-7 8.5 7V21h-6v-6h-5v6h-5V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Home</span></a>
            <a class="footer-link" href="{{ route('mobile.home') }}#tickets"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4h12v17H6zM9 4V2h6v2M9 9h6M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>My Tickets</span></a>
            <a class="footer-link" href="{{ route('mobile.home') }}#tasks"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4h12v17H6zM9 4V2h6v2M9 9h6M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Tasks</span></a>
            <a class="footer-link active" href="{{ route('mobile.account') }}" aria-current="page"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.7"/><path d="M5 21a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg><span>Account</span></a>
        </nav>
    </footer>
</body>
</html>
