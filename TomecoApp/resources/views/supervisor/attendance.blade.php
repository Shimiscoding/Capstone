@extends('layouts.dashboard')

@section('title', 'Attendance Management')
@section('activePage', 'attendance')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    @php
        $roleLabel = match ($attendanceType) { 'enforcer' => 'Enforcer', 'admin' => 'Admin', default => 'Supervisor' };
        $legacyHeading = $roleLabel.' Attendance';
    @endphp
    <div class="page-head users-page-head">
        <div class="users-heading-copy">
            <span class="sr-only">{{ $legacyHeading }}</span>
            <h1><span class="management-subject">{{ $roleLabel }}</span> attendance <span class="users-heading-count">{{ number_format($staffMembers->total()) }}</span></h1>
            <p class="page-description">Manage individual Time In and Time Out restrictions for {{ strtolower(\Illuminate\Support\Str::plural($roleLabel)) }}.</p>
        </div>
    </div>

    @if (session('attendance_success'))<div class="success-message" role="status">{{ session('attendance_success') }}</div>@endif
    @if ($errors->any())<div class="success-message" role="alert">{{ $errors->first() }}</div>@endif

    <form class="user-table-filters users-reference-toolbar" method="GET" action="{{ route('dashboard.attendance') }}" role="search">
        <input type="hidden" name="type" value="{{ $attendanceType }}">
        <div class="users-toolbar-main"><div class="users-toolbar-actions">
            <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search users</span><input type="search" name="search" value="{{ $search }}" placeholder="Search"></label>
            <label class="toolbar-customize"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2 6.5 3.8v7.5L10 18l-6.5-4.7V5.8z"/><circle cx="10" cy="10" r="2.3"/></svg><span class="sr-only">Sort users</span><select name="sort" onchange="this.form.submit()"><option value="latest" @selected($sort === 'latest')>Customize</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="name_asc" @selected($sort === 'name_asc')>Name A–Z</option><option value="name_desc" @selected($sort === 'name_desc')>Name Z–A</option></select></label>
            <button class="toolbar-outline-button" type="button">Export</button>
        </div></div>
    </form>

    <section class="table-card" aria-live="polite"><div class="table-scroll">
        <table class="users-table attendance-table"><thead><tr>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path d="M4 17c.6-4 2.6-6 6-6s5.4 2 6 6"/></svg>Full name</span></th>
            <th>Role</th><th>Records</th><th>Time In window</th><th>Time Out window</th><th>Working days</th><th>Status</th><th>Actions</th>
        </tr></thead><tbody>
        @forelse ($staffMembers as $staff)
            @php
                $staffName = $staff->fullName ?: 'Unnamed user';
                $initials = collect(explode(' ', $staffName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
                $days = $staff->attendance_working_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                $timeInStart = substr((string) ($staff->attendance_time_in_start ?: '06:00'), 0, 5);
                $timeInEnd = substr((string) ($staff->attendance_time_in_end ?: '09:00'), 0, 5);
                $timeOutStart = substr((string) ($staff->attendance_time_out_start ?: '16:00'), 0, 5);
                $timeOutEnd = substr((string) ($staff->attendance_time_out_end ?: '20:00'), 0, 5);
                $detailsRoute = $staff->isSupervisor()
                    ? route('dashboard.users.supervisors.show', $staff)
                    : ($staff->isOfficer()
                        ? route('dashboard.users.enforcers.show', $staff)
                        : route('dashboard.users.admins.show', $staff));
                $attendanceRoute = $staff->isSupervisor()
                    ? route('dashboard.attendance.supervisor', $staff)
                    : ($staff->isOfficer() ? route('supervisor.enforcers.attendance', $staff) : null);
                $nameRoute = $attendanceRoute ?? $detailsRoute;
            @endphp
            <tr>
                <td><div class="user-cell"><span class="table-avatar">{{ $initials ?: 'U' }}</span><span><strong><a class="user-name-link" href="{{ $nameRoute }}">{{ $staffName }}</a></strong><small>{{ $staff->username ? '@'.$staff->username : $staff->email }}</small></span></div></td>
                <td class="user-role-text">{{ $staff->isOfficer() ? 'Enforcer' : ucfirst($staff->role) }}</td>
                <td>{{ number_format($staff->isSupervisor() ? $staff->supervisor_attendances_count : ($staff->isOfficer() ? $staff->enforcer_attendances_count : 0)) }}</td>
                <td>{{ $staff->attendance_restrictions_enabled ? \Illuminate\Support\Carbon::createFromFormat('H:i', $timeInStart)->format('h:i A').' – '.\Illuminate\Support\Carbon::createFromFormat('H:i', $timeInEnd)->format('h:i A') : '—' }}</td>
                <td>{{ $staff->attendance_restrictions_enabled ? \Illuminate\Support\Carbon::createFromFormat('H:i', $timeOutStart)->format('h:i A').' – '.\Illuminate\Support\Carbon::createFromFormat('H:i', $timeOutEnd)->format('h:i A') : '—' }}</td>
                <td>{{ $staff->attendance_restrictions_enabled ? collect($days)->map(fn($day) => ucfirst(substr($day, 0, 3)))->join(', ') : 'Any day' }}</td>
                <td><span class="status-badge {{ $staff->attendance_restrictions_enabled ? '' : 'status-inactive' }}"><i></i>{{ $staff->attendance_restrictions_enabled ? 'Restricted' : 'Open' }}</span></td>
                <td><div class="user-row-actions"><details class="user-actions-menu"><summary aria-label="Attendance actions" title="Actions">&#8942;</summary><div class="user-actions-dropdown">
                    <span class="sr-only">View details</span>
                    <button class="user-action action-edit" type="button" data-open-attendance-modal data-name-encoded="{{ base64_encode($staffName) }}" data-action="{{ route('dashboard.attendance.restrictions.update', $staff) }}" data-enabled="{{ $staff->attendance_restrictions_enabled ? '1' : '0' }}" data-time-in-start="{{ $timeInStart }}" data-time-in-end="{{ $timeInEnd }}" data-time-out-start="{{ $timeOutStart }}" data-time-out-end="{{ $timeOutEnd }}" data-days='@json($days)'>Set restrictions</button>
                </div></details></div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-state"><strong>{{ $search !== '' ? 'No users found' : 'No registered users yet' }}</strong><span>{{ $search !== '' ? 'Try a different name.' : 'Users will appear here after registration.' }}</span></td></tr>
        @endforelse
        </tbody></table>
    </div><div class="table-footer"><span>Showing {{ $staffMembers->firstItem() ?? 0 }}–{{ $staffMembers->lastItem() ?? 0 }} of {{ $staffMembers->total() }}</span><div class="pagination-actions">@if ($staffMembers->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Previous</span>@else<a class="page-button" href="{{ $staffMembers->previousPageUrl() }}">Previous</a>@endif @if ($staffMembers->hasMorePages())<a class="page-button" href="{{ $staffMembers->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled" aria-disabled="true">Next</span>@endif</div></div></section>

    <dialog class="attendance-restriction-modal" id="attendanceRestrictionModal" aria-labelledby="attendanceRestrictionTitle">
        <div class="attendance-modal-head"><div><span>Attendance schedule</span><h2 id="attendanceRestrictionTitle">Set restrictions</h2><p id="attendanceRestrictionUser"></p></div><button type="button" data-close-attendance-modal aria-label="Close modal">&times;</button></div>
        <form class="attendance-restriction-form" id="attendanceRestrictionForm" method="POST">@csrf @method('PUT')
            <label class="attendance-restriction-toggle"><input type="checkbox" name="attendance_restrictions_enabled" value="1"> Enable restrictions for this user</label>
            <div class="attendance-restriction-times"><label><span>Time In from</span><input type="time" name="attendance_time_in_start" required></label><label><span>Time In until</span><input type="time" name="attendance_time_in_end" required></label><label><span>Time Out from</span><input type="time" name="attendance_time_out_start" required></label><label><span>Time Out until</span><input type="time" name="attendance_time_out_end" required></label></div>
            <fieldset><legend>Working days</legend><div class="attendance-day-options">@foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)<label><input type="checkbox" name="attendance_working_days[]" value="{{ $day }}"><span>{{ ucfirst(substr($day, 0, 3)) }}</span></label>@endforeach</div></fieldset>
            <div class="attendance-modal-actions"><button type="button" class="page-button" data-close-attendance-modal>Cancel</button><button type="submit">Save restrictions</button></div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('attendanceRestrictionModal');
    const form = document.getElementById('attendanceRestrictionForm');
    const userLabel = document.getElementById('attendanceRestrictionUser');
    document.querySelectorAll('[data-open-attendance-modal]').forEach(button => button.addEventListener('click', () => {
        form.action = button.dataset.action;
        userLabel.textContent = atob(button.dataset.nameEncoded);
        form.elements.attendance_restrictions_enabled.checked = button.dataset.enabled === '1';
        form.elements.attendance_time_in_start.value = button.dataset.timeInStart;
        form.elements.attendance_time_in_end.value = button.dataset.timeInEnd;
        form.elements.attendance_time_out_start.value = button.dataset.timeOutStart;
        form.elements.attendance_time_out_end.value = button.dataset.timeOutEnd;
        const days = JSON.parse(button.dataset.days || '[]');
        form.querySelectorAll('[name="attendance_working_days[]"]').forEach(input => input.checked = days.includes(input.value));
        button.closest('details')?.removeAttribute('open');
        modal.showModal();
    }));
    modal.querySelectorAll('[data-close-attendance-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
})();
</script>
@endpush
