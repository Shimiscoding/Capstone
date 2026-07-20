<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impounded Vehicles | TOMECO</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
     <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>

<body>
  @php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->fullName))
      ->filter()
      ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
      ->take(2)
      ->join('');
  @endphp

  <div class="app-frame">
    <header class="topbar">
      <div class="brand-switcher">
        <img class="brand-logo" src="{{ asset('images/favicon.ico') }}" alt="TOMECO logo">
        <div class="brand-title">TOMECO</div>
        <button class="plain-button" aria-label="Switch business">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 9 4-4 4 4"/><path d="m16 15-4 4-4-4"/></svg>
        </button>
      </div>

      <label class="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="impound-search" type="search" placeholder="Search impounded vehicles" aria-label="Search impounded vehicles">
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
          <p class="section-label">Dashboard</p>
           <a class="nav-item" href="{{ route('dashboard') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-6h4v6h5v-9.5"/></svg>
            <span class="nav-text">Home dashboard</span>
          </a>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19V5"/><path d="m7 15 4-4 3 3 5-6"/></svg>
            <span class="nav-text">Analytics</span>
          </button>
          <a class="nav-item" href="{{ route('dashboard.payments') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>
            <span class="nav-text">Payments</span>
          </a>
          <a class="nav-item" href="{{ route('dashboard.users') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="nav-text">Users</span>
          </a>
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
          <a class="nav-item is-active" href="{{ route('dashboard.impounding') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="m8 9 4 3 4-3"/></svg>
            <span class="nav-text">Impound Vehicles</span>
          </a>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1-1"/></svg>
            <span class="nav-text">Checkout links</span>
          </button>
          <button class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h10l2 2v16l-3-2-3 2-3-2-3 2-2-1V5Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
            <span class="nav-text">Summon</span>
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
        <div class="page-head users-page-head">
          <div>
            <p class="eyebrow">Vehicle management</p>
            <h1>Impounded Vehicles</h1>
            <p class="page-description">Track vehicles held in TOMECO facilities and monitor their release status.</p>
          </div>
       
        </div>

        <div class="table-card">
          <div class="table-scroll">
            <table class="users-table" id="impound-table">
              <thead>
                <tr><th>Reference</th><th>Owner / Vehicle</th><th>Plate number</th><th>Violation</th><th>Date impounded</th><th>Location</th><th>Status</th></tr>
              </thead>
              <tbody>
                @foreach ($vehicles as $vehicle)
                  <tr>
                    <td><span class="badge-number">{{ $vehicle['reference'] }}</span></td>
                    <td><div class="user-cell"><span class="vehicle-icon">VEH</span><span><strong>{{ $vehicle['owner'] }}</strong><small>{{ $vehicle['vehicle'] }}</small></span></div></td>
                    <td><strong>{{ $vehicle['plate'] }}</strong></td>
                    <td>{{ $vehicle['violation'] }}</td>
                    <td>{{ $vehicle['date'] }}</td>
                    <td>{{ $vehicle['location'] }}</td>
                    <td><span class="impound-status status-{{ \Illuminate\Support\Str::slug($vehicle['status']) }}"><i></i>{{ $vehicle['status'] }}</span></td>
                  </tr>
                @endforeach
                <tr id="impound-empty" hidden><td colspan="7" class="empty-state"><strong>No vehicles found</strong><span>Try another reference, owner, plate number, or status.</span></td></tr>
              </tbody>
            </table>
          </div>
          <div class="table-footer"><span>Showing <strong id="impound-visible-count">{{ $vehicles->count() }}</strong> of {{ $vehicles->count() }} vehicles</span></div>
        </div>
      </section>
    </main>
  </div>
  <script>
    const impoundSearch = document.getElementById('impound-search');
    const vehicleRows = [...document.querySelectorAll('#impound-table tbody tr:not(#impound-empty)')];
    const emptyRow = document.getElementById('impound-empty');
    const visibleCount = document.getElementById('impound-visible-count');
    impoundSearch.addEventListener('input', () => {
      const query = impoundSearch.value.trim().toLowerCase();
      let matches = 0;
      vehicleRows.forEach(row => {
        const visible = row.textContent.toLowerCase().includes(query);
        row.hidden = !visible;
        if (visible) matches++;
      });
      emptyRow.hidden = matches !== 0;
      visibleCount.textContent = matches;
    });
  </script>
</body>
</html>
