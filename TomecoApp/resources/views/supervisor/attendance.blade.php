@extends('layouts.admin-dashboard')

@section('title', 'Attendance Management')
@section('activePage', 'attendance')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    @php
        $roleLabel = $attendanceType === 'enforcer' ? 'Enforcer' : 'Supervisor';
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
                $timeOutStart = substr((string) ($staff->attendance_time_out_start ?: ($staff->isOfficer() ?  '11:30' : '16:00')), 0, 5);
                $timeOutEnd = substr((string) ($staff->attendance_time_out_end ?: ($staff->isOfficer() ? '12:00' : '20:00')), 0, 5);
                $afternoon = collect(['in_start' => '13:00', 'in_end' => '14:00', 'out_start' => '16:00', 'out_end' => '18:00'])->mapWithKeys(fn ($default, $key) => [$key => substr((string) ($staff->{'attendance_afternoon_time_'.$key} ?: $default), 0, 5)])->all();
                $detailsRoute = $staff->isSupervisor()
                    ? route('dashboard.users.supervisors.show', $staff)
                    : ($staff->isOfficer()
                        ? route('dashboard.users.enforcers.show', $staff)
                        : route('dashboard.users.admins.show', $staff));
                $attendanceRoute = $staff->isSupervisor()
                    ? route('dashboard.attendance.supervisor', $staff)
                    : ($staff->isOfficer() ? route('dashboard.attendance.enforcers.show', $staff) : null);
                $nameRoute = $attendanceRoute ?? $detailsRoute;
            @endphp
            <tr>
                <td><div class="user-cell"><x-user-avatar :user="$staff" /><span><strong><a class="user-name-link" href="{{ $nameRoute }}">{{ $staffName }}</a></strong><small>{{ $staff->username ? '@'.$staff->username : $staff->email }}</small></span></div></td>
                <td class="user-role-text">{{ $staff->isOfficer() ? 'Enforcer' : ucfirst($staff->role) }}</td>
                <td>{{ number_format($staff->isSupervisor() ? $staff->supervisor_attendances_count : ($staff->isOfficer() ? $staff->enforcer_attendances_count : 0)) }}</td>
                @foreach (['in' => [$timeInStart, $timeInEnd], 'out' => [$timeOutStart, $timeOutEnd]] as $action => $window)
                    <td>
                        @if ($staff->isOfficer())<small>Morning</small>@endif
                        <div>{{ $staff->attendance_restrictions_enabled ? \Illuminate\Support\Carbon::createFromFormat('H:i', $window[0])->format('h:i A').' - '.\Illuminate\Support\Carbon::createFromFormat('H:i', $window[1])->format('h:i A') : 'Not set' }}</div>
                        @if ($staff->isOfficer())
                            <div class="attendance-afternoon-window"><small>Afternoon</small></div>
                            <div>{{ $staff->attendance_restrictions_enabled && $staff->{'attendance_afternoon_time_'.$action.'_start'} && $staff->{'attendance_afternoon_time_'.$action.'_end'} ? \Illuminate\Support\Carbon::createFromFormat('H:i', $afternoon[$action.'_start'])->format('h:i A').' - '.\Illuminate\Support\Carbon::createFromFormat('H:i', $afternoon[$action.'_end'])->format('h:i A') : 'Not set' }}</div>
                        @endif
                    </td>
                @endforeach
                <td>{{ $staff->attendance_restrictions_enabled ? collect($days)->map(fn($day) => ucfirst(substr($day, 0, 3)))->join(', ') : 'Not set' }}</td>
                <td><span class="status-badge {{ $staff->attendance_restrictions_enabled ? '' : 'status-inactive' }}"><i></i>{{ $staff->attendance_restrictions_enabled ? 'Restricted' : 'Not set' }}</span></td>
                <td><div class="user-row-actions"><details class="user-actions-menu"><summary aria-label="Attendance actions" title="Actions">&#8942;</summary><div class="user-actions-dropdown">
                    <span class="sr-only">View details</span>
                    <button class="user-action action-edit" type="button" data-open-attendance-modal data-user-id="{{ $staff->id }}" data-officer="{{ $staff->isOfficer() ? '1' : '0' }}" data-afternoon='@json($afternoon)' data-name-encoded="{{ base64_encode($staffName) }}" data-action="{{ route('dashboard.attendance.restrictions.update', $staff) }}" data-enabled="{{ $staff->attendance_restrictions_enabled ? '1' : '0' }}" data-time-in-start="{{ $timeInStart }}" data-time-in-end="{{ $timeInEnd }}" data-time-out-start="{{ $timeOutStart }}" data-time-out-end="{{ $timeOutEnd }}" data-days='@json($days)'>Set restrictions</button>
                </div></details></div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-state"><strong>{{ $search !== '' ? 'No users found' : 'No registered users yet' }}</strong><span>{{ $search !== '' ? 'Try a different name.' : 'Users will appear here after registration.' }}</span></td></tr>
        @endforelse
        </tbody></table>
    </div><div class="table-footer"><span>Showing {{ $staffMembers->firstItem() ?? 0 }}–{{ $staffMembers->lastItem() ?? 0 }} of {{ $staffMembers->total() }}</span><div class="pagination-actions">@if ($staffMembers->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Previous</span>@else<a class="page-button" href="{{ $staffMembers->previousPageUrl() }}">Previous</a>@endif @if ($staffMembers->hasMorePages())<a class="page-button" href="{{ $staffMembers->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled" aria-disabled="true">Next</span>@endif</div></div></section>

    <dialog class="attendance-restriction-modal" id="attendanceRestrictionModal" aria-labelledby="attendanceRestrictionTitle">
        <div class="attendance-modal-head">
            <div><h2 id="attendanceRestrictionTitle">Set attendance restrictions</h2><p>Set the days and times this user can record attendance.</p></div>
            <button type="button" data-close-attendance-modal aria-label="Close modal"><span aria-hidden="true">&times;</span></button>
        </div>
        <form class="attendance-restriction-form" id="attendanceRestrictionForm" method="POST" novalidate>@csrf @method('PUT')
            <input type="hidden" name="attendance_user_id" id="attendanceRestrictionUserId">
            <div id="attendanceRestrictionErrors" role="alert" tabindex="-1" hidden></div>
            <label class="attendance-restriction-toggle">
                <span class="attendance-toggle-copy"><strong>Restrict attendance</strong><small>Only allow records during the schedule below.</small></span>
                <span class="attendance-toggle-control"><input type="checkbox" name="attendance_restrictions_enabled" value="1" aria-label="Restrict attendance"><i aria-hidden="true"></i></span>
            </label>

            <div class="attendance-user-row"><span>User</span><strong id="attendanceRestrictionUser"></strong></div>

            <div class="attendance-schedule-fields">
                <strong id="morningDutyLabel" hidden>Morning duty</strong>
                <div class="attendance-time-row">
                    <div class="attendance-time-label"><strong>Time In</strong><span>Allowed clock-in window</span></div>
                    <div class="attendance-restriction-times"><label><span>From</span><input type="time" name="attendance_time_in_start" required></label><span class="attendance-time-separator">to</span><label><span>Until</span><input type="time" name="attendance_time_in_end" required></label></div>
                </div>
                <div class="attendance-time-row">
                    <div class="attendance-time-label"><strong>Time Out</strong><span>Allowed clock-out window</span></div>
                    <div class="attendance-restriction-times"><label><span>From</span><input type="time" name="attendance_time_out_start" required></label><span class="attendance-time-separator">to</span><label><span>Until</span><input type="time" name="attendance_time_out_end" required></label></div>
                </div>
                <fieldset id="afternoonDutyFields" hidden><legend>Afternoon duty</legend>
                    @foreach (['in' => 'Time In', 'out' => 'Time Out'] as $action => $label)
                        <div class="attendance-time-row">
                            <div class="attendance-time-label"><strong>{{ $label }}</strong><span>Afternoon window</span></div>
                            <div class="attendance-restriction-times"><label><span>From</span><input type="time" name="attendance_afternoon_time_{{ $action }}_start"></label><span class="attendance-time-separator">to</span><label><span>Until</span><input type="time" name="attendance_afternoon_time_{{ $action }}_end"></label></div>
                        </div>
                    @endforeach
                </fieldset>
                <fieldset><legend>Working days</legend><p>Select at least one day.</p><div class="attendance-day-options">@foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)<label><input type="checkbox" name="attendance_working_days[]" value="{{ $day }}"><span>{{ ucfirst(substr($day, 0, 3)) }}</span></label>@endforeach</div></fieldset>
            </div>
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
        form.elements.attendance_user_id.value = button.dataset.userId;
        document.getElementById('attendanceRestrictionErrors').hidden = true;
        form.querySelectorAll('[aria-invalid]').forEach(input => { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); });
        userLabel.textContent = atob(button.dataset.nameEncoded);
        form.elements.attendance_restrictions_enabled.checked = button.dataset.enabled === '1';
        form.elements.attendance_time_in_start.value = button.dataset.timeInStart;
        form.elements.attendance_time_in_end.value = button.dataset.timeInEnd;
        form.elements.attendance_time_out_start.value = button.dataset.timeOutStart;
        form.elements.attendance_time_out_end.value = button.dataset.timeOutEnd;
        const officer = button.dataset.officer === '1';
        document.getElementById('morningDutyLabel').hidden = !officer;
        document.getElementById('afternoonDutyFields').hidden = !officer;
        const afternoon = JSON.parse(button.dataset.afternoon);
        Object.entries(afternoon).forEach(([key, value]) => {
            const input = form.elements['attendance_afternoon_time_' + key];
            input.value = value;
            input.disabled = !officer;
            input.required = officer;
        });
        const days = JSON.parse(button.dataset.days || '[]');
        form.querySelectorAll('[name="attendance_working_days[]"]').forEach(input => input.checked = days.includes(input.value));
        button.closest('details')?.removeAttribute('open');
        modal.showModal();
    }));
    const errorBox = document.getElementById('attendanceRestrictionErrors');
    const savedInput = @json(session()->getOldInput());
    const serverErrors = @json($errors->messages());
    const savedButton = Array.from(document.querySelectorAll('[data-open-attendance-modal]')).find(button => button.dataset.userId === String(savedInput.attendance_user_id));
    if (savedButton && Object.keys(serverErrors).length) {
        savedButton.click();
        Array.from(form.elements).forEach(input => {
            if (!(input.name in savedInput)) return;
            if (input.type === 'checkbox') input.checked = String(savedInput[input.name]) === input.value;
            else input.value = savedInput[input.name];
        });
        form.elements.attendance_restrictions_enabled.checked = savedInput.attendance_restrictions_enabled === '1';
        form.querySelectorAll('[name="attendance_working_days[]"]').forEach(input => input.checked = (savedInput.attendance_working_days || []).includes(input.value));
        errorBox.textContent = Object.values(serverErrors).flat().join(' ');
        errorBox.hidden = false;
        Object.keys(serverErrors).forEach(name => {
            const input = form.elements[name];
            if (input && input.setAttribute) { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', errorBox.id); }
        });
        errorBox.focus();
    }
    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
    modal.querySelectorAll('[data-close-attendance-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
})();
</script>
@endpush
