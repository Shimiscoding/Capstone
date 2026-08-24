@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('activePage', 'dashboard')

@section('content')
  <div class="page-head home-page-head">
    <div><p class="eyebrow">TOMECO operations overview</p><h1>Home</h1><p class="page-description">Welcome, {{ auth()->user()->fullName }}. Review issued tickets, payment status, and impounded vehicles.</p></div>
    <div class="dashboard-date">{{ now()->format('F d, Y') }}</div>
  </div>

  <div class="overview-grid">
    <article class="overview-card overview-card-primary"><div class="overview-icon">T</div><div><p>{{ $isDriverDashboard ? 'My issued tickets' : 'Tickets issued' }}</p><strong>{{ number_format($totalTickets) }}</strong><span>{{ number_format($outstandingTickets) }} unpaid or pending</span></div></article>
    <article class="overview-card"><div class="overview-icon">V</div><div><p>{{ $isDriverDashboard ? 'My impounded vehicles' : 'Vehicles currently impounded' }}</p><strong>{{ number_format($impoundedVehicles) }}</strong><span>{{ number_format($forReleaseVehicles) }} ready for release</span></div></article>
    <article class="overview-card"><div class="overview-icon">U</div><div><p>Registered users</p><strong>{{ number_format($totalUsers) }}</strong><span>Driver, officer, and admin accounts</span></div></article>
  </div>

  <div class="home-content-grid">
    <section class="dashboard-panel">
      <div class="panel-heading"><div><h2>Recently issued tickets</h2><p>Latest traffic violations recorded in TOMECO</p></div><a href="{{ route('dashboard.payments') }}">Review tickets</a></div>
      <div class="recent-list">
        @forelse ($recentViolations as $violation)
          <div class="recent-user"><span class="table-avatar">T</span><span class="recent-details"><strong>{{ $violation->violation_type }}</strong><small>{{ $violation->driver_name }} · {{ $violation->plate_number }} · ₱{{ number_format($violation->fine_amount, 2) }}</small></span><span class="recent-date">{{ ucfirst($violation->status) }}<br>{{ $violation->created_at?->format('M d, Y') }}</span></div>
        @empty
          <div class="home-empty">No tickets have been issued yet.</div>
        @endforelse
      </div>
    </section>
    <aside class="dashboard-panel quick-panel">
      <div class="panel-heading"><div><h2>Recently impounded</h2><p>Latest vehicles received at TOMECO yards</p></div><a href="{{ route('dashboard.impounding') }}">View all</a></div>
      @forelse ($recentImpoundedVehicles as $vehicle)
        <a class="quick-action" href="{{ route('dashboard.impounding') }}"><span><strong>{{ $vehicle->plate }}</strong><br><small>{{ $vehicle->owner }} · {{ $vehicle->vehicle }}</small></span><small>{{ $vehicle->impounded_at?->format('M d') }}</small></a>
      @empty
        <div class="home-empty">No impounded vehicles recorded.</div>
      @endforelse
      <div class="panel-heading"><div><h2>Quick actions</h2><p>Open operational records</p></div></div>
      <a class="quick-action" href="{{ route('dashboard.payments') }}"><span>Review tickets and payments</span><b>&rarr;</b></a>
      <a class="quick-action" href="{{ route('dashboard.impounding') }}"><span>Manage impounded vehicles</span><b>&rarr;</b></a>
      @if (auth()->user()->isAdmin())<a class="quick-action" href="{{ route('dashboard.users') }}"><span>Manage users</span><b>&rarr;</b></a>@endif
    </aside>
  </div>

  @if(auth()->user()->isAdmin())
    <section class="table-card admin-attendance-panel">
      <div class="supervisor-list-heading"><div><p class="eyebrow">Supervisor attendance</p><h2>Time In / Time Out Records</h2></div><span class="user-count">Latest {{ $supervisorAttendances->count() }}</span></div>
      <div class="table-scroll"><table class="users-table attendance-table">
        <thead><tr><th>Supervisor</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($supervisorAttendances as $attendance)
            <tr><td><div class="user-cell"><span class="table-avatar">{{ collect(explode(' ', $attendance->user->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?: 'S' }}</span><span><strong><a class="user-name-link" href="{{ route('dashboard.users.supervisors.show', $attendance->user) }}">{{ $attendance->user->fullName }}</a></strong><small>{{ $attendance->user->username ? '@'.$attendance->user->username : $attendance->user->email }}</small></span></div></td><td>{{ $attendance->attendance_date->format('M d, Y') }}</td><td>{{ $attendance->time_in?->format('h:i A') ?? '—' }}</td><td>{{ $attendance->time_out?->format('h:i A') ?? '—' }}</td><td>{{ $attendance->time_in && $attendance->time_out ? number_format($attendance->time_in->diffInMinutes($attendance->time_out) / 60, 2).' hrs' : '—' }}</td><td><span class="attendance-state {{ $attendance->time_out ? 'is-complete' : 'is-active' }}">{{ $attendance->time_out ? 'Completed' : 'Timed in' }}</span></td></tr>
          @empty
            <tr><td colspan="6" class="empty-state"><strong>No supervisor attendance yet</strong><span>Records appear after supervisors use Time In.</span></td></tr>
          @endforelse
        </tbody>
      </table></div>
    </section>
  @endif
@endsection
