<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#991b1b">
    <title>Traffic Violation Mobile Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/mobile-home.css') }}?v={{ filemtime(public_path('css/mobile-home.css')) }}">
</head>
<body>
    <main class="mobile-dashboard">
        <section class="hero-area">
            <header class="topbar">
                <div class="officer-profile">
                    <img
                        class="officer-avatar"
                        src="{{ asset('pwa/icons/android/launchericon-192x192.png') }}"
                        alt="TOMECO officer"
                    >
                    <div>
                        <p>Good Morning</p>
                        <h1>{{ Auth::user()->name ?? 'Officer Admin' }}</h1>
                    </div>
                </div>

                <button class="menu-button" type="button" aria-label="Open menu">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 8h12M6 12h12M6 16h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </header>

            <div class="search-line">
                <label class="search-box" for="ticket-search">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
                        <path d="m16 16 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <input id="ticket-search" type="search" placeholder="Search Tickets">
                </label>
                <button class="filter-button" type="button" aria-label="Ticket filters">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 5v14M12 8v8M17 5v14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="primary-actions" role="group" aria-label="Ticket actions">
                <button type="button">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 6h14v12H5zM8 9h8M8 13h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                   Issue Tickets
                </button>
                <button type="button">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 6h14v12H5zM8 9h8M8 13h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    View Violations
                </button>
            </div>
        </section>

        <section class="dashboard-content" id="tickets">
            <h2>Ticket Status</h2>
            <div class="status-list">
                <article class="status-card">
                    <span class="status-number new">10</span>
                    <p>New Tickets</p>
                </article>
                <article class="status-card">
                    <span class="status-number progress">9</span>
                    <p>In Progress</p>
                </article>
                <article class="status-card">
                    <span class="status-number closed">24</span>
                    <p>Closed Tickets</p>
                </article>
            </div>

            <h2 id="tasks">Recent Violations</h2>
            <div class="task-list">
                @foreach ([
                    ['name' => 'No Helmet Violation', 'date' => '12/03/2025', 'status' => 'Closed'],
                    ['name' => 'Illegal Parking', 'date' => '12/03/2025', 'status' => 'Closed'],
                    ['name' => 'Overspeeding Report', 'date' => '12/04/2025', 'status' => 'Closed'],
                    ['name' => 'Reckless Driving', 'date' => '12/04/2025', 'status' => 'Closed'],
                ] as $task)
                    <article class="task-card">
                        <span class="task-icon">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.25" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M5.5 20a6.5 6.5 0 0 1 13 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <div class="task-details">
                            <h3>{{ $task['name'] }}</h3>
                            <p>Review the submitted traffic violation details and supporting evidence.</p>
                            <div class="task-meta">
                                <span><i>i</i> Assigned Date&nbsp; {{ $task['date'] }}</span>
                                <span class="closed-badge">{{ $task['status'] }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="mobile-footer">
        <nav class="footer-nav" aria-label="Mobile navigation">
            <a class="footer-link active" href="{{ route('mobile.home') }}" aria-current="page">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m3.5 10.5 8.5-7 8.5 7V21h-6v-6h-5v6h-5V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Home</span>
            </a>

            <a class="footer-link" href="#tickets">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 4h12v17H6zM9 4V2h6v2M9 9h6M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>My Tickets</span>
            </a>

            <a class="footer-link" href="#tasks">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 4h12v17H6zM9 4V2h6v2M9 9h6M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Tasks</span>
            </a>

            <a class="footer-link" href="{{ route('mobile.account') }}">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M5 21a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
                <span>Account</span>
            </a>
        </nav>
    </footer>
</body>
</html>
