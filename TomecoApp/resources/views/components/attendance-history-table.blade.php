@props(['staff', 'attendances', 'enforcer' => false])
@php
    $canManage = auth()->user()->isAdmin();
    $routePrefix = $enforcer ? 'dashboard.attendance.enforcers' : 'dashboard.attendance.supervisors';
@endphp
<section class="table-card attendance-records-card">
    <div class="supervisor-list-heading">
        <div class="user-cell"><x-user-avatar :user="$staff" /><span><strong>{{ $staff->fullName }}</strong><small>{{ $staff->username ? '@'.$staff->username.' · ' : '' }}{{ $staff->email }}</small></span></div>
    </div>
    <div class="table-scroll attendance-records-scroll" tabindex="0" role="region" aria-label="Attendance history table">
        <table class="users-table attendance-table attendance-records-table">
            <caption class="attendance-visually-hidden">Attendance history for {{ $staff->fullName }}</caption>
            <thead><tr>
                <th scope="col">Date</th>
                @if ($enforcer)<th scope="col">Duty</th>@endif
                <th scope="col">Time in</th><th scope="col">Time out</th><th scope="col">Total hours</th><th scope="col">Status</th>
                @if ($canManage)<th scope="col">Actions</th>@endif
            </tr></thead>
            <tbody>
                @forelse ($attendances as $attendance)
                    <tr>
                        <th scope="row"><span class="attendance-cell-value">{{ $attendance->attendance_date->format('M d, Y') }}</span><small class="attendance-cell-detail">{{ $attendance->attendance_date->format('l') }}</small></th>
                        @if ($enforcer)<td>{{ ucfirst($attendance->session) }}</td>@endif
                        @foreach (['time_in' => 'Time in', 'time_out' => 'Time out'] as $field => $label)
                            <td>
                                <span class="attendance-cell-value">{{ $attendance->$field?->format('h:i:s A') ?? '—' }}</span>
                                @if ($enforcer)
                                    @if ($attendance->{$field.'_latitude'} !== null && $attendance->{$field.'_longitude'} !== null)
                                        <a class="attendance-cell-detail attendance-map-link" href="https://www.google.com/maps?q={{ $attendance->{$field.'_latitude'} }},{{ $attendance->{$field.'_longitude'} }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }} location for {{ $attendance->attendance_date->format('M d, Y') }}, {{ $attendance->session }} duty (opens in a new tab)">Open map</a>
                                    @else
                                        <small class="attendance-cell-detail">{{ $attendance->$field ? 'Location unavailable' : 'Not recorded' }}</small>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                        <td>{{ $attendance->time_in && $attendance->time_out ? number_format($attendance->time_in->diffInMinutes($attendance->time_out) / 60, 2).' hrs' : '—' }}</td>
                        <td><span class="attendance-state {{ $attendance->time_out ? 'is-complete' : 'is-active' }}">{{ $attendance->time_out ? 'Completed' : 'Timed in' }}</span></td>
                        @if ($canManage)
                            <td><div class="attendance-row-actions">
                                <button type="button" class="attendance-record-button" data-attendance-edit data-url="{{ route($routePrefix.'.time-out', $attendance) }}" data-label="{{ $attendance->attendance_date->format('l, M d, Y') }}{{ $enforcer ? ' · '.ucfirst($attendance->session).' duty' : '' }}" data-time-in="{{ $attendance->time_in?->format('H:i') }}" data-time-out="{{ $attendance->time_out?->format('H:i') }}" aria-label="Edit attendance for {{ $attendance->attendance_date->format('M d, Y') }}{{ $enforcer ? ', '.$attendance->session.' duty' : '' }}">Edit</button>
                                <button type="button" class="attendance-record-button is-danger" data-attendance-delete data-url="{{ route($routePrefix.'.destroy', $attendance) }}" data-label="{{ $attendance->attendance_date->format('l, M d, Y') }}{{ $enforcer ? ' · '.ucfirst($attendance->session).' duty' : '' }}" aria-label="Delete attendance for {{ $attendance->attendance_date->format('M d, Y') }}{{ $enforcer ? ', '.$attendance->session.' duty' : '' }}">Delete</button>
                            </div></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + (int) $enforcer + (int) $canManage }}" class="empty-state"><strong>No timestamp records</strong><span>This {{ $enforcer ? 'enforcer' : 'supervisor' }} has not recorded attendance yet.</span></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($attendances->hasPages())
        <div class="table-footer"><span>Showing {{ $attendances->firstItem() }}–{{ $attendances->lastItem() }} of {{ $attendances->total() }}</span><div class="pagination-actions">{{ $attendances->links() }}</div></div>
    @endif
</section>

@if ($canManage)
    <dialog class="attendance-restriction-modal attendance-record-dialog" id="attendanceEditDialog" aria-labelledby="attendanceEditTitle" aria-describedby="attendanceEditDescription">
        <header class="attendance-modal-head"><div><h2 id="attendanceEditTitle">Edit attendance</h2><p id="attendanceEditDescription" data-record-label></p></div><button type="button" data-close-dialog aria-label="Close attendance editor">&times;</button></header>
        <form method="POST" class="attendance-record-form" novalidate>
            @csrf @method('PUT')
            <div class="attendance-record-fields">
                <p class="attendance-record-error" role="alert" tabindex="-1" hidden></p>
                <label for="attendanceRecordTimeIn">Time in<input id="attendanceRecordTimeIn" type="time" name="time_in" required></label>
                <label for="attendanceRecordTimeOut">Time out<input id="attendanceRecordTimeOut" type="time" name="time_out" required></label>
            </div>
            <footer class="attendance-modal-actions"><button type="button" data-close-dialog>Cancel</button><button type="submit">Save changes</button></footer>
        </form>
    </dialog>
    <dialog class="attendance-restriction-modal attendance-record-dialog" id="attendanceDeleteDialog" aria-labelledby="attendanceDeleteTitle" aria-describedby="attendanceDeleteDescription">
        <header class="attendance-modal-head"><div><h2 id="attendanceDeleteTitle">Delete attendance record?</h2><p>{{ $staff->fullName }}</p></div><button type="button" data-close-dialog aria-label="Close delete confirmation">&times;</button></header>
        <form method="POST" class="attendance-record-form" novalidate>
            @csrf @method('DELETE')
            <input type="hidden" name="page" value="{{ $attendances->currentPage() }}">
            <div class="attendance-record-fields"><p class="attendance-record-error" role="alert" tabindex="-1" hidden></p><p data-record-label></p><p id="attendanceDeleteDescription">This removes this attendance record from the history and totals. This cannot be undone. The deletion remains in the audit log.</p></div>
            <footer class="attendance-modal-actions"><button type="button" data-close-dialog autofocus>Cancel</button><button type="submit">Delete record</button></footer>
        </form>
    </dialog>
    @push('scripts')
        <script src="{{ asset('js/attendance-history.js') }}?v={{ filemtime(public_path('js/attendance-history.js')) }}" defer></script>
    @endpush
@endif
