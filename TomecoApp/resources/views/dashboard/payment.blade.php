<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Overview | TOMECO</title>
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
   <link rel="icon" href="{{ asset('images/favicon.ico') }}">
</head>
<body>
  @php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->fullName ?: 'User'))->filter()
      ->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
    $transactions = [
      ['reference' => 'PAY-2026-0148', 'payer' => 'Juan Dela Cruz', 'type' => 'Traffic violation', 'date' => 'Jul 14, 2026', 'amount' => '₱1,500.00', 'status' => 'Paid'],
      ['reference' => 'PAY-2026-0147', 'payer' => 'Maria Santos', 'type' => 'Parking violation', 'date' => 'Jul 14, 2026', 'amount' => '₱500.00', 'status' => 'Pending'],
      ['reference' => 'PAY-2026-0146', 'payer' => 'Carlo Reyes', 'type' => 'Traffic violation', 'date' => 'Jul 13, 2026', 'amount' => '₱2,000.00', 'status' => 'Paid'],
      ['reference' => 'PAY-2026-0145', 'payer' => 'Angela Lim', 'type' => 'Impound fee', 'date' => 'Jul 13, 2026', 'amount' => '₱3,500.00', 'status' => 'Failed'],
      ['reference' => 'PAY-2026-0144', 'payer' => 'Miguel Garcia', 'type' => 'Parking violation', 'date' => 'Jul 12, 2026', 'amount' => '₱750.00', 'status' => 'Paid'],
    ];
  @endphp

  <div class="app-frame">
    <header class="topbar">
      <div class="brand-switcher">
        <img class="brand-logo" src="{{ asset('images/favicon.ico') }}" alt="TOMECO logo">
        <div class="brand-title">TOMECO</div>
      </div>
      <label class="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" placeholder="Search payments" aria-label="Search payments">
      </label>
      <div class="topbar-spacer"></div>
      <div class="amount-pill">{{ $user->email }}</div>
      <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="logout-top-button">Log out</button></form>
      <button class="profile" aria-label="Account">{{ $initials ?: 'U' }}</button>
    </header>

  <aside class="sidebar" aria-label="Main navigation">
      <div class="sidebar-scroll">

        <nav class="nav-section">
          <p class="section-label">Dashboard</p>
           <a class="nav-item is-active" href="{{ route('dashboard') }}">
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
        <div class="page-head payment-page-head">
          <div><p class="eyebrow">Financial overview</p><h1>Payments</h1><p class="page-description">Monitor collections and recent payment activity.</p></div>
          <div class="payment-period"><span>Period</span><strong>July 2026</strong></div>
        </div>

        <div class="payment-stats">
          <article class="payment-stat is-primary"><p>Total collected</p><strong>₱128,750.00</strong><span class="trend-up">↑ 12.4% from last month</span></article>
          <article class="payment-stat"><p>Successful payments</p><strong>86</strong><span>91.5% completion rate</span></article>
          <article class="payment-stat"><p>Pending payments</p><strong>8</strong><span class="trend-warn">₱12,250.00 awaiting payment</span></article>
          <article class="payment-stat"><p>Failed payments</p><strong>3</strong><span class="trend-down">Needs attention</span></article>
        </div>

        <div class="payment-layout">
          <section class="dashboard-panel collection-panel">
            <div class="panel-heading"><div><h2>Collection activity</h2><p>Payment totals for the last seven days</p></div><span class="chart-total">₱42,500</span></div>
            <div class="bar-chart" aria-label="Static weekly collection chart">
              @foreach ([42, 67, 51, 78, 59, 88, 72] as $height)
                <div class="bar-column"><div class="bar-track"><span style="height: {{ $height }}%"></span></div><small>{{ ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$loop->index] }}</small></div>
              @endforeach
            </div>
          </section>
          <aside class="dashboard-panel breakdown-panel">
            <div class="panel-heading"><div><h2>Payment breakdown</h2><p>By collection type</p></div></div>
            <div class="breakdown-row"><span><i class="dot red"></i>Traffic violations</span><strong>58%</strong></div>
            <div class="breakdown-row"><span><i class="dot orange"></i>Parking violations</span><strong>27%</strong></div>
            <div class="breakdown-row"><span><i class="dot gray"></i>Impound fees</span><strong>15%</strong></div>
          </aside>
        </div>

        <section class="table-card payment-table-card">
          <div class="panel-heading"><div><h2>Recent transactions</h2><p>Latest payment activity</p></div><button class="outline-action" type="button">Export</button></div>
          <div class="table-scroll"><table class="users-table"><thead><tr><th>Reference</th><th>Payer</th><th>Payment type</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            @foreach ($transactions as $transaction)
              <tr><td><span class="payment-reference">{{ $transaction['reference'] }}</span></td><td><strong>{{ $transaction['payer'] }}</strong></td><td>{{ $transaction['type'] }}</td><td>{{ $transaction['date'] }}</td><td class="payment-amount">{{ $transaction['amount'] }}</td><td><span class="payment-status status-{{ strtolower($transaction['status']) }}">{{ $transaction['status'] }}</span></td></tr>
            @endforeach
          </tbody></table></div>
        </section>
      </section>
    </main>
  </div>
</body>
</html>
