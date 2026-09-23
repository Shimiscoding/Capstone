@extends(auth()->user()->isAdmin() ? 'layouts.admin-dashboard' : 'layouts.supervisor-dashboard')

@section('title', $enforcer->fullName . ' Attendance')
@section('activePage', auth()->user()->isAdmin() ? 'attendance' : 'enforcer-attendance')

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">Attendance history</p>
            <h1>{{ $enforcer->fullName }}</h1>
            <p class="page-description">Complete Time In and Time Out timestamp history for this enforcer.</p>
        </div>
        <div class="users-page-actions"><a class="page-button detail-back-button"
                href="{{ auth()->user()->isAdmin() ? route('dashboard.attendance', ['type' => 'enforcer']) : route('supervisor.enforcers.attendance.index') }}"
                aria-label="Back to attendance" title="Back to attendance">&larr;</a><span
                class="user-count">{{ number_format($attendances->total()) }} records</span></div>
    </div>

    @if (session('attendance_success'))
        <div class="success-message" role="status">{{ session('attendance_success') }}</div>
    @endif

    <section class="table-card attendance-edit-card">
        <div class="supervisor-list-heading">
            <div class="user-cell"><span
                    class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?:'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@' . $enforcer->username . ' · ' : '' }}{{ $enforcer->email }}</small></span>
            </div>
        </div>
        <div class="table-scroll attendance-edit-table-scroll">
            <table class="users-table attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time In Location</th>
                        <th>Time Out</th>
                        <th>Time Out Location</th>
                        <th>Total Hours</th>
                        <th>Status</th>
                        @if (auth()->user()->isAdmin())
                            <th>Update Attendance</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->attendance_date->format('l, M d, Y') }}</td>
                            <td>{{ $attendance->time_in?->format('h:i:s A') ?? '—' }}</td>
                            <td>
                                @if ($attendance->time_in_latitude !== null && $attendance->time_in_longitude !== null)
                                    <a href="https://www.google.com/maps?q={{ $attendance->time_in_latitude }},{{ $attendance->time_in_longitude }}"
                                        target="_blank" rel="noopener noreferrer">Open map</a>
                                @else
                                    <span>Unavailable</span>
                                @endif
                            </td>
                            <td>{{ $attendance->time_out?->format('h:i:s A') ?? '—' }}</td>
                            <td>
                                @if ($attendance->time_out_latitude !== null && $attendance->time_out_longitude !== null)
                                    <a href="https://www.google.com/maps?q={{ $attendance->time_out_latitude }},{{ $attendance->time_out_longitude }}"
                                        target="_blank" rel="noopener noreferrer">Open map</a>
                                @else
                                    <span>Unavailable</span>
                                @endif
                            </td>
                            <td>{{ $attendance->time_in && $attendance->time_out ? number_format($attendance->time_in->diffInMinutes($attendance->time_out) / 60, 2) . ' hrs' : '—' }}
                            </td>
                            <td><span
                                    class="attendance-state {{ $attendance->time_out ? 'is-complete' : 'is-active' }}">{{ $attendance->time_out ? 'Completed' : 'Timed in' }}</span>
                            </td>
                            @if (auth()->user()->isAdmin())
                                <td>
                                    <details class="attendance-edit-menu">
                                        <summary aria-label="Edit attendance" title="Edit attendance">&#8942;</summary>
                                        <div class="attendance-edit-dropdown">
                                            <form class="attendance-time-edit" method="POST"
                                                action="{{ route('dashboard.attendance.enforcers.time-out', $attendance) }}">
                                                @csrf
                                                @method('PUT')
                                                <label>Time In<input type="time" name="time_in"
                                                        value="{{ $attendance->time_in?->format('H:i') }}" required></label>
                                                <label>Time Out<input type="time" name="time_out"
                                                        value="{{ $attendance->time_out?->format('H:i') }}"
                                                        min="{{ $attendance->time_in?->format('H:i') }}" required></label>
                                                <button type="submit">Update Attendance</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="empty-state"><strong>No timestamp records</strong><span>This enforcer
                                    has not recorded attendance yet.</span></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($attendances->hasPages())
            <div class="table-footer"><span>Showing {{ $attendances->firstItem() }}–{{ $attendances->lastItem() }} of
                    {{ $attendances->total() }}</span>
                <div class="pagination-actions">{{ $attendances->links() }}</div>
            </div>
        @endif
    </section>
@endsection
