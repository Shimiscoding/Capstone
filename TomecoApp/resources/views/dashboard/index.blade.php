<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>

<body>
  @php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))
      ->filter()
      ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
      ->take(2)
      ->join('');
  @endphp

  <div class="app-frame">
    <header class="topbar">
      <div class="brand-switcher">
        <div class="mark" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="course-avatar">TM</div>
        <div class="brand-title">TomecoApp</div>
        <button class="plain-button" aria-label="Switch business">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 9 4-4 4 4"/><path d="m16 15-4 4-4-4"/></svg>
        </button>
      </div>

      <label class="search" aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" placeholder="Search dashboard">
      </label>

      <div class="topbar-spacer"></div>

      <button class="icon-button notif" aria-label="Messages">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.2A8 8 0 1 1 21 12Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg>
      </button>
      <button class="icon-button" aria-label="Shortcuts">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m13 2-2 8h8L9 22l2-8H5L13 2Z"/></svg>
      </button>
      <button class="icon-button" aria-label="Notifications">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M10 21h4"/></svg>
      </button>
      <div class="amount-pill">{{ $user->email }}</div>
      <form method="POST" action="{{ route('logout') }}" class="logout-form">
        @csrf
        <button type="submit" class="logout-top-button">Log out</button>
      </form>
      <button class="profile" aria-label="Account">{{ $initials ?: 'U' }}</button>
    </header>

    <aside class="sidebar" aria-label="Main navigation">
      <div class="sidebar-scroll">
        <nav class="nav-section">
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-6h4v6h5v-9.5"/></svg>
            <span class="nav-text">Business home</span>
          </button>
        </nav>

        <nav class="nav-section">
          <p class="section-label">Dashboard</p>
          <button class="nav-item is-active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19V5"/><path d="m7 15 4-4 3 3 5-6"/></svg>
            <span class="nav-text">Analytics</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>
            <span class="nav-text">Payments</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="nav-text">Users</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 10h16"/><path d="M5 10 12 4l7 6"/><path d="M6 10v8M10 10v8M14 10v8M18 10v8"/><path d="M3 18h18"/></svg>
            <span class="nav-text">Balances</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            <span class="nav-text">Support chats</span>
          </button>
        </nav>

        <nav class="nav-section">
          <p class="section-label">Pinned</p>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m8 9 4 3 4-3"/></svg>
            <span class="nav-text">Impound Vehicles</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1-1"/></svg>
            <span class="nav-text">Checkout links</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h10l2 2v16l-3-2-3 2-3-2-3 2-2-1V5Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
            <span class="nav-text">Invoices</span>
          </button>
        </nav>

        <nav class="nav-section">
          <p class="section-label">All tools</p>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 12h16"/><path d="M8 4v16"/><path d="M16 4v16"/><path d="M6 8h12M6 16h12"/></svg>
            <span class="nav-text">Marketing</span>
            <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h.01M12 12h.01M19 12h.01"/></svg>
            <span class="nav-text">More</span>
            <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 9 6 6 6-6"/></svg>
          </button>
        </nav>

        <nav class="nav-section">
          <p class="section-label">Apps</p>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
            <span class="nav-text">Automations</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
            <span class="nav-text">Add</span>
          </button>
        </nav>
      </div>

      <div class="sidebar-footer">
        <button class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m8 9-4 3 4 3"/><path d="m16 9 4 3-4 3"/><path d="m14 5-4 14"/></svg>
          <span class="nav-text">Developer</span>
        </button>
        <button class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.3 7A2 2 0 1 1 7.1 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1a2 2 0 0 1 0 4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>
          <span class="nav-text">Settings</span>
        </button>
      </div>
    </aside>

    <main class="content">
      <section class="workspace">
        <div class="page-head">
          <h1>Welcome, {{ $user->name }}</h1>
          <button class="add-apps">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><path d="M7 14v6M4 17h6M17 14v6M14 17h6"/></svg>
            Add apps
          </button>
        </div>

        <div class="starter-area">
          <div>
            <div class="metric-row">
              <div>
                <p class="metric-label">Account status <span class="badge">Active</span></p>
                <p class="metric-value">Signed in</p>
                <p class="metric-note">{{ now()->format('h:i A') }}</p>
              </div>
              <div>
                <p class="metric-label" style="color:#73777d;">Member since</p>
                <p class="metric-value" style="color:#84888e;">{{ $user->created_at->format('M d, Y') }}</p>
              </div>
            </div>
            <div class="placeholder-chart">
              <svg class="blue-line" viewBox="0 0 340 120" aria-hidden="true">
                <polyline points="0,105 210,105 240,105 270,10 325,10"></polyline>
              </svg>
            </div>
          </div>

          <aside class="side-summary">
            <div class="summary-block">
              <div class="summary-row">
                <p class="metric-label">Profile</p>
                <button class="link-button">View</button>
              </div>
              <p class="metric-value">{{ $user->name }}</p>
              <p class="metric-note">{{ $user->email }}</p>
            </div>
            <div class="summary-block">
              <div class="summary-row">
                <p class="metric-label">Session</p>
                <button class="link-button" style="color:#5f6368;">View</button>
              </div>
              <p class="metric-value" style="color:#8a8e93;">Protected</p>
            </div>
          </aside>
        </div>
      </section>
    </main>
  </div>
</body>
</html>

