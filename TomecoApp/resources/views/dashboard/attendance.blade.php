@extends('layouts.dashboard')

@section('title', 'Supervisor Attendance')
@section('activePage', 'attendance')

@section('content')
  <div class="page-head users-page-head"><div><p class="eyebrow">Attendance management</p><h1>Supervisor Attendance</h1><p class="page-description">Review supervisor Time In and Time Out records.</p></div><div class="user-count">{{ number_format($attendances->total()) }} records</div></div>

  <section class="table-card">
    <div class="table-scroll"><table class="users-table attendance-table">
      <thead><tr><th>Supervisor</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Total Hours</th><th>Status</th></tr></thead>
      <tbody>
        @forelse($attendances as $attendance)
          <tr><td><div class="user-cell"><span class="table-avatar">{{ collect(explode(' ', $attendance->user->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?: 'S' }}</span><span><strong><a class="user-name-link" href="{{ route('dashboard.users.supervisors.show', $attendance->user) }}">{{ $attendance->user->fullName }}</a></strong><small>{{ $attendance->user->username ? '@'.$attendance->user->username : $attendance->user->email }}</small></span></div></td><td>{{ $attendance->attendance_date->format('M d, Y') }}</td><td>{{ $attendance->time_in?->format('h:i A') ?? '—' }}</td><td>{{ $attendance->time_out?->format('h:i A') ?? '—' }}</td><td>{{ $attendance->time_in && $attendance->time_out ? number_format($attendance->time_in->diffInMinutes($attendance->time_out) / 60, 2).' hrs' : '—' }}</td><td><span class="attendance-state {{ $attendance->time_out ? 'is-complete' : 'is-active' }}">{{ $attendance->time_out ? 'Completed' : 'Timed in' }}</span></td></tr>
        @empty
          <tr><td colspan="6" class="empty-state"><strong>No supervisor attendance yet</strong><span>Records appear after a supervisor uses Time In.</span></td></tr>
        @endforelse
      </tbody>
    </table></div>
    @if($attendances->hasPages())<div class="table-footer"><span>Showing {{ $attendances->firstItem() }}–{{ $attendances->lastItem() }} of {{ $attendances->total() }}</span><div class="pagination-actions">{{ $attendances->links() }}</div></div>@endif
  </section>
@endsection
