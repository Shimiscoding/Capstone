<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Dashboard') | TOMECO</title>
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
  <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>
<body>
  @php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->fullName ?: 'User'))->filter()
      ->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
    $activePage = trim($__env->yieldContent('activePage', 'dashboard'));
  @endphp

  <div class="app-frame">
    <header class="topbar">
      <div class="brand-switcher">
        <img class="brand-logo" src="{{ asset('images/favicon.ico') }}" alt="TOMECO logo">
        <div class="brand-title">TOMECO</div>
        <button class="plain-button sidebar-toggle" id="sidebarToggle" type="button" aria-label="Hide sidebar" aria-controls="dashboardSidebar" aria-expanded="true">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 9 4-4 4 4"/><path d="m16 15-4 4-4-4"/></svg>
        </button>
      </div>

      @hasSection('headerSearch')
        @yield('headerSearch')
      @else
        <div class="search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="search" placeholder="Search dashboard" aria-label="Search dashboard">
        </div>
      @endif

      <div class="topbar-spacer"></div>
      <button class="icon-button notif" type="button" aria-label="Messages"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.2A8 8 0 1 1 21 12Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg></button>
      <button class="icon-button" type="button" aria-label="Shortcuts"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m13 2-2 8h8L9 22l2-8H5L13 2Z"/></svg></button>
      @include('dashboard.partials.notifications')
      <div class="amount-pill">{{ $user->email }}</div>
      <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="logout-top-button">Log out</button></form>
      <button class="profile" type="button" aria-label="Account">{{ $initials ?: 'U' }}</button>
    </header>

    <aside class="sidebar" id="dashboardSidebar" aria-label="Main navigation">
      <div class="sidebar-scroll">
        <nav class="nav-section">
          <p class="section-label">Dashboard</p>
          <a class="nav-item {{ $activePage === 'dashboard' ? 'is-active' : '' }}" href="{{ route('dashboard') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-6h4v6h5v-9.5"/></svg><span class="nav-text">Home   </span></a>
          @unless($user->isSupervisor())
          <button class="nav-item" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19V5"/><path d="m7 15 4-4 3 3 5-6"/></svg><span class="nav-text">Analytics</span></button>
          <a class="nav-item {{ $activePage === 'payments' ? 'is-active' : '' }}" href="{{ route('dashboard.payments') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg><span class="nav-text">Payments</span></a>
          <a class="nav-item {{ $activePage === 'driver-violators' ? 'is-active' : '' }}" href="{{ route('dashboard.driver-violators') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h10l2 2v16l-3-2-4 2-4-2-3 2V5Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg><span class="nav-text">Driver Violators</span></a>
          <a class="nav-item {{ $activePage === 'impounding' ? 'is-active' : '' }}" href="{{ route('dashboard.impounding') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m8 9 4 3 4-3"/></svg><span class="nav-text">Impounded Vehicles</span></a>
          @endunless
          {{-- <button class="nav-item" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 10h16"/><path d="M5 10 12 4l7 6"/><path d="M6 10v8M10 10v8M14 10v8M18 10v8"/><path d="M3 18h18"/></svg><span class="nav-text">Summon</span></button> --}}
        </nav>
        <nav class="nav-section">
          <p class="section-label">Pinned</p>
          <button class="nav-item" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span class="nav-text">Support chats</span></button>
          @if($user->isAdmin())
          <details class="nav-dropdown" @if(in_array($activePage, ['users-supervisors', 'users-enforcers', 'users-admins'], true)) open @endif>
            <summary class="nav-item {{ str_starts_with($activePage, 'users-') ? 'is-active' : '' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg><span class="nav-text">Users</span><span class="nav-dropdown-arrow">›</span></summary>
            <div class="nav-submenu"><a class="nav-subitem {{ $activePage === 'users-supervisors' ? 'is-active' : '' }}" href="{{ route('dashboard.users.supervisors') }}">Supervisors</a><a class="nav-subitem {{ $activePage === 'users-enforcers' ? 'is-active' : '' }}" href="{{ route('dashboard.users.enforcers') }}">Enforcers</a><a class="nav-subitem {{ $activePage === 'users-admins' ? 'is-active' : '' }}" href="{{ route('dashboard.users.admins') }}">Admins</a></div>
          </details>
          <a class="nav-item {{ $activePage === 'attendance' ? 'is-active' : '' }}" href="{{ route('dashboard.attendance') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span class="nav-text">Attendance</span></a>
          @endif
          {{-- <button class="nav-item" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h10l2 2v16l-3-2-3 2-3-2-3 2-2-1V5Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg><span class="nav-text">Summon Form</span></button> --}}
        </nav>
        
      </div>
      <div class="sidebar-footer">
        <a class="nav-item {{ $activePage === 'profile' ? 'is-active' : '' }}" href="{{ route('profile.edit') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span class="nav-text">My Profile</span></a>
        @if ($user->isAdmin())<a class="nav-item {{ $activePage === 'settings' ? 'is-active' : '' }}" href="{{ route('dashboard.settings') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14a2 2 0 0 1 0-4 1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9A2 2 0 1 1 7.1 4.2a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3a2 2 0 0 1 4 0 1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3A2 2 0 1 1 19.8 7a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1 2 2 0 0 1 0 4 1.7 1.7 0 0 0-1.7 1Z"/></svg><span class="nav-text">Settings</span></a>@endif
      </div>
    </aside>

    <main class="content"><section class="workspace">@if(app(\App\Services\Settings::class)->get('maintenance.enabled', false))<div class="payment-alert is-error">Scheduled maintenance is enabled. Administrative access remains available.</div>@endif @yield('content')</section></main>
  </div>
  <script>
    (() => {
      const frame = document.querySelector('.app-frame');
      const toggle = document.getElementById('sidebarToggle');
      let sidebarHidden = false;
      try { sidebarHidden = localStorage.getItem('tomeco-sidebar-hidden') === 'true'; } catch (error) {}

      const setSidebar = (hidden) => {
        frame.classList.toggle('is-sidebar-hidden', hidden);
        toggle.setAttribute('aria-expanded', String(!hidden));
        toggle.setAttribute('aria-label', hidden ? 'Show sidebar' : 'Hide sidebar');
        try { localStorage.setItem('tomeco-sidebar-hidden', String(hidden)); } catch (error) {}
      };

      setSidebar(sidebarHidden);
      toggle.addEventListener('click', () => {
        sidebarHidden = !frame.classList.contains('is-sidebar-hidden');
        setSidebar(sidebarHidden);
      });

      document.querySelectorAll('.success-message[role="status"], .payment-alert.is-success').forEach((message) => {
        window.setTimeout(() => {
          message.classList.add('is-dismissing');
          message.addEventListener('transitionend', () => message.remove(), { once: true });
          window.setTimeout(() => message.remove(), 400);
        }, 4000);
      });
    })();
  </script>
  @stack('scripts')
</body>
</html>
