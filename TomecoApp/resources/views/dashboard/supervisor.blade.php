@extends('layouts.dashboard')

@section('title', 'Supervisor Dashboard')
@section('activePage', 'dashboard')

@section('content')
  <div class="page-head home-page-head">
    <div><p class="eyebrow">Supervisor workspace</p><h1>Welcome, {{ $supervisor->firstName }}</h1><p class="page-description">Review your assigned enforcement team and their coverage information.</p></div>
    <div class="dashboard-date">{{ now()->format('F d, Y') }}</div>
  </div>

  @if(session('attendance_success'))<div class="success-message" role="status">{{ session('attendance_success') }}</div>@endif
  @if(session('attendance_error'))<div class="payment-alert is-error" role="alert">{{ session('attendance_error') }}</div>@endif

  <section class="attendance-panel">
    <div class="attendance-summary">
      <div><p class="eyebrow">Today's attendance</p><h2>{{ now()->format('l, F j') }}</h2><span class="attendance-state {{ $todayAttendance?->time_out ? 'is-complete' : ($todayAttendance?->time_in ? 'is-active' : '') }}">{{ $todayAttendance?->time_out ? 'Completed' : ($todayAttendance?->time_in ? 'Timed in' : 'Not timed in') }}</span></div>
      <div class="attendance-times"><div><span>Time in</span><strong>{{ $todayAttendance?->time_in?->format('h:i A') ?? '—' }}</strong></div><div><span>Time out</span><strong>{{ $todayAttendance?->time_out?->format('h:i A') ?? '—' }}</strong></div></div>
      <div class="attendance-actions">
        <form method="POST" action="{{ route('supervisor.time-in') }}">@csrf<button type="submit" @disabled($todayAttendance?->time_in)>Time In</button></form>
        <form method="POST" action="{{ route('supervisor.time-out') }}">@csrf<button class="time-out-button" type="submit" @disabled(!$todayAttendance?->time_in || $todayAttendance?->time_out)>Time Out</button></form>
      </div>
    </div>
    <div class="attendance-history"><h3>Recent attendance</h3><div class="table-scroll"><table class="users-table attendance-table"><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th><th>Hours</th></tr></thead><tbody>@forelse($recentAttendances as $attendance)<tr><td>{{ $attendance->attendance_date->format('M d, Y') }}</td><td>{{ $attendance->time_in?->format('h:i A') ?? '—' }}</td><td>{{ $attendance->time_out?->format('h:i A') ?? '—' }}</td><td><span class="attendance-state {{ $attendance->time_out ? 'is-complete' : 'is-active' }}">{{ $attendance->time_out ? 'Completed' : 'Timed in' }}</span></td><td>{{ $attendance->time_in && $attendance->time_out ? number_format($attendance->time_in->diffInMinutes($attendance->time_out) / 60, 2).' hrs' : '—' }}</td></tr>@empty<tr><td colspan="5" class="empty-state"><strong>No attendance records yet</strong><span>Your records will appear after you time in.</span></td></tr>@endforelse</tbody></table></div></div>
  </section>

  <div class="overview-grid supervisor-overview-grid">
    <article class="overview-card overview-card-primary"><div class="overview-icon">E</div><div><p>Assigned enforcers</p><strong>{{ number_format($enforcerCount) }}</strong><span>Members currently under your supervision</span></div></article>
    <article class="overview-card"><div class="overview-icon">B</div><div><p>Barangays covered</p><strong>{{ number_format($barangayCount) }}</strong><span>Unique barangays represented by your team</span></div></article>
    <article class="overview-card"><div class="overview-icon">A</div><div><p>Areas covered</p><strong>{{ number_format($areaCount) }}</strong><span>Unique operational areas represented</span></div></article>
  </div>

  <section class="table-card supervisor-team-panel">
    <div class="supervisor-list-heading"><div><p class="eyebrow">My team</p><h2>Assigned enforcers</h2></div><span class="user-count">{{ $enforcerCount }} {{ \Illuminate\Support\Str::plural('enforcer', $enforcerCount) }}</span></div>
    <div class="table-scroll"><table class="users-table">
      <thead><tr><th>Enforcer</th><th>Contact</th><th>Barangay</th><th>Area and address</th><th>Status</th></tr></thead>
      <tbody>
        @forelse($supervisor->enforcers as $enforcer)
          <tr>
            <td><div class="user-cell"><span class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?: 'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@'.$enforcer->username : 'No username' }}</small></span></div></td>
            <td>{{ $enforcer->phoneNumber ?: '—' }}<br><small>{{ $enforcer->email }}</small></td>
            <td>{{ $enforcer->barangay ?: '—' }}</td>
            <td class="location-cell" title="{{ collect([$enforcer->area, $enforcer->address])->filter()->join(', ') }}"><strong>{{ $enforcer->area ?: '—' }}</strong><small class="location-truncate">{{ $enforcer->address ?: 'No address' }}</small></td>
            <td><span class="status-badge"><i></i> Registered</span></td>
          </tr>
        @empty
          <tr><td colspan="5" class="empty-state"><strong>No enforcers assigned yet</strong><span>An administrator must assign enforcers to your account.</span></td></tr>
        @endforelse
      </tbody>
    </table></div>
  </section>
@endsection
