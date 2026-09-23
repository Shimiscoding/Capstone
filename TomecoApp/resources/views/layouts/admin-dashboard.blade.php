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
        $initials = collect(explode(' ', $user->fullName ?: 'User'))
            ->filter()
            ->map(fn($part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->join('');
        $activePage = trim($__env->yieldContent('activePage', 'dashboard'));
    @endphp

    <div class="app-frame">
        <header class="topbar">
            <div class="brand-switcher">
                <img class="brand-logo" src="{{ asset('images/favicon.ico') }}" alt="TOMECO logo">
                <div class="brand-title"><strong>TOMECO</strong><span>Traffic Operations</span></div>
                <button class="plain-button sidebar-toggle" id="sidebarToggle" type="button" aria-label="Hide sidebar"
                    aria-controls="dashboardSidebar" aria-expanded="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <path d="M4 7h16" />
                        <path d="M4 12h16" />
                        <path d="M4 17h16" />
                    </svg>
                </button>
            </div>

            @hasSection('headerSearch')
                @yield('headerSearch')
            @else
                <div class="search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                    <input type="search" placeholder="Search dashboard" aria-label="Search dashboard">
                </div>
            @endif

            <div class="topbar-spacer"></div>
            @include('dashboard.partials.notifications')
            <div class="profile-menu" id="profileMenu">
                <button class="profile" id="profileMenuToggle" type="button" aria-label="Open account menu"
                    aria-haspopup="menu" aria-expanded="false">{{ $initials ?: 'U' }}</button>
                <div class="profile-dropdown" id="profileDropdown" role="menu" hidden>
                    <div class="profile-dropdown-user">
                        <strong>{{ $user->fullName ?: 'User' }}</strong><small>{{ $user->email }}</small></div>
                    <a href="{{ route('profile.edit') }}" role="menuitem"><span>My Profile</span></a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" role="menuitem">Sign
                            out</button></form>
                </div>
            </div>
        </header>

        <aside class="sidebar" id="dashboardSidebar" aria-label="Main navigation">
            <div class="sidebar-scroll">
                <nav class="nav-section">
                    <p class="section-label">Operations</p>
                    <a class="nav-item {{ $activePage === 'dashboard' ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11.5 12 4l9 7.5" /><path d="M5 10.5V20h5v-6h4v6h5v-9.5" /></svg>
                        <span class="nav-text">Home</span>
                    </a>
                    <a class="nav-item {{ $activePage === 'analytics' ? 'is-active' : '' }}" href="{{ route('dashboard.analytics') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19V5" /><path d="m7 15 4-4 3 3 5-6" /></svg>
                        <span class="nav-text">Analytics</span>
                    </a>
                    <a class="nav-item {{ $activePage === 'violation-records' ? 'is-active' : '' }}" href="{{ route('dashboard.violation-records') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h10l2 2v16l-3-2-4 2-4-2-3 2V5Z" /><path d="M9 8h6M9 12h6M9 16h4" /></svg>
                        <span class="nav-text">Violation Records</span>
                    </a>
                    <a class="nav-item {{ $activePage === 'location-monitoring' ? 'is-active' : '' }}" href="{{ route('location-monitoring.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z" /><circle cx="12" cy="9" r="2.5" /></svg>
                        <span class="nav-text">Location Monitoring</span>
                    </a>
                </nav>

                <nav class="nav-section">
                    <p class="section-label">Administration</p>
                    <details class="nav-dropdown" @if (str_starts_with($activePage, 'users')) open @endif>
                        <summary class="nav-item {{ str_starts_with($activePage, 'users') ? 'is-active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21a8 8 0 0 0-16 0" /><circle cx="12" cy="7" r="4" /></svg>
                            <span class="nav-text">Users</span><span class="nav-dropdown-arrow">&rsaquo;</span>
                        </summary>
                        <div class="nav-submenu">
                            <a class="nav-subitem {{ $activePage === 'users-supervisors' ? 'is-active' : '' }}" href="{{ route('dashboard.users.supervisors') }}">Supervisors</a>
                            <a class="nav-subitem {{ $activePage === 'users-enforcers' ? 'is-active' : '' }}" href="{{ route('dashboard.users.enforcers') }}">Enforcers</a>
                            <a class="nav-subitem {{ $activePage === 'users-admins' ? 'is-active' : '' }}" href="{{ route('dashboard.users.admins') }}">Admins</a>
                        </div>
                    </details>
                    <details class="nav-dropdown" @if ($activePage === 'area-assignments') open @endif>
                        <summary class="nav-item {{ $activePage === 'area-assignments' ? 'is-active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20h16M6 17V7l6-4 6 4v10" /><path d="M9 10h6M9 14h6" /></svg>
                            <span class="nav-text">Area Assignments</span><span class="nav-dropdown-arrow">&rsaquo;</span>
                        </summary>
                        <div class="nav-submenu">
                            <a class="nav-subitem {{ request('type') === 'supervisor' ? 'is-active' : '' }}" href="{{ route('area-assignments.index', ['type' => 'supervisor']) }}">Supervisors</a>
                            <a class="nav-subitem {{ request('type', 'officer') === 'officer' ? 'is-active' : '' }}" href="{{ route('area-assignments.index', ['type' => 'officer']) }}">Enforcers</a>
                        </div>
                    </details>
                    <details class="nav-dropdown" @if ($activePage === 'attendance') open @endif>
                        <summary class="nav-item {{ $activePage === 'attendance' ? 'is-active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                            <span class="nav-text">Attendance</span><span class="nav-dropdown-arrow">&rsaquo;</span>
                        </summary>
                        <div class="nav-submenu">
                            <a class="nav-subitem {{ request('type', 'supervisor') === 'supervisor' ? 'is-active' : '' }}" href="{{ route('dashboard.attendance', ['type' => 'supervisor']) }}">Supervisors</a>
                            <a class="nav-subitem {{ request('type') === 'enforcer' ? 'is-active' : '' }}" href="{{ route('dashboard.attendance', ['type' => 'enforcer']) }}">Enforcers</a>
                        </div>
                    </details>
                    <a class="nav-item {{ $activePage === 'audit-logs' ? 'is-active' : '' }}" href="{{ route('dashboard.audit-logs') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h9l3 3v15H6z"/><path d="M9 10h6M9 14h6M9 18h4M14 3v4h4"/></svg>
                        <span class="nav-text">Audit Log</span>
                    </a>
                </nav>
            </div>
            <div class="sidebar-footer">
                <a class="nav-item {{ $activePage === 'profile' ? 'is-active' : '' }}" href="{{ route('profile.edit') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
                    <span class="nav-text">My Profile</span>
                </a>
                <a class="nav-item {{ $activePage === 'settings' ? 'is-active' : '' }}" href="{{ route('dashboard.settings') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14a2 2 0 0 1 0-4 1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9A2 2 0 1 1 7.1 4.2a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3a2 2 0 0 1 4 0 1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3A2 2 0 1 1 19.8 7a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1 2 2 0 0 1 0 4 1.7 1.7 0 0 0-1.7 1Z" /></svg>
                    <span class="nav-text">Settings</span>
                </a>
            </div>
        </aside>

        <main class="content">
            <section class="workspace @yield('workspaceClass')">
                @yield('content')
            </section>
        </main>
    </div>
    <script>
        (() => {
            const frame = document.querySelector('.app-frame');
            const toggle = document.getElementById('sidebarToggle');
            let sidebarHidden = false;
            try {
                sidebarHidden = localStorage.getItem('tomeco-sidebar-hidden') === 'true';
            } catch (error) {}

            const setSidebar = (hidden) => {
                frame.classList.toggle('is-sidebar-hidden', hidden);
                toggle.setAttribute('aria-expanded', String(!hidden));
                toggle.setAttribute('aria-label', hidden ? 'Show sidebar' : 'Hide sidebar');
                try {
                    localStorage.setItem('tomeco-sidebar-hidden', String(hidden));
                } catch (error) {}
            };

            setSidebar(sidebarHidden);
            toggle.addEventListener('click', () => {
                sidebarHidden = !frame.classList.contains('is-sidebar-hidden');
                setSidebar(sidebarHidden);
            });

            const profileMenu = document.getElementById('profileMenu');
            const profileMenuToggle = document.getElementById('profileMenuToggle');
            const profileDropdown = document.getElementById('profileDropdown');
            const closeProfileMenu = () => {
                profileDropdown.hidden = true;
                profileMenuToggle.setAttribute('aria-expanded', 'false');
            };

            profileMenuToggle.addEventListener('click', () => {
                const willOpen = profileDropdown.hidden;
                profileDropdown.hidden = !willOpen;
                profileMenuToggle.setAttribute('aria-expanded', String(willOpen));
            });
            document.addEventListener('click', (event) => {
                if (!profileMenu.contains(event.target)) closeProfileMenu();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeProfileMenu();
            });

            document.querySelectorAll('.success-message[role="status"], .flash-alert.is-success').forEach((
            message) => {
                window.setTimeout(() => {
                    message.classList.add('is-dismissing');
                    message.addEventListener('transitionend', () => message.remove(), {
                        once: true
                    });
                    window.setTimeout(() => message.remove(), 400);
                }, 4000);
            });
        })();
    </script>
    @stack('scripts')
</body>

</html>
