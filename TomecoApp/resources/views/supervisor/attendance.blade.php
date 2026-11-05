@extends('layouts.admin-dashboard')

@section('title', 'Attendance Management')
@section('activePage', 'attendance')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    @php
        $roleLabel = 'Enforcer';
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

    <form class="user-table-filters users-reference-toolbar" method="GET" action="{{ route('dashboard.attendance') }}" role="search" novalidate>
        <input type="hidden" name="type" value="{{ $attendanceType }}">
        <div class="users-toolbar-main"><div class="users-toolbar-actions">
            <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search users</span><input type="search" name="search" value="{{ $search }}" placeholder="Search" aria-label="Search users"><button type="button" data-clear-attendance-search aria-label="Clear search" @if ($search === '') hidden @endif>&times;</button></label>
            <label class="toolbar-customize"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2 6.5 3.8v7.5L10 18l-6.5-4.7V5.8z"/><circle cx="10" cy="10" r="2.3"/></svg><span class="sr-only">Sort users</span><select name="sort" onchange="this.form.submit()"><option value="latest" @selected($sort === 'latest')>Customize</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="name_asc" @selected($sort === 'name_asc')>Name A–Z</option><option value="name_desc" @selected($sort === 'name_desc')>Name Z–A</option></select></label>
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
                $nameRoute = route('dashboard.attendance.enforcers.show', $staff);
            @endphp
            <tr>
                <td><div class="user-cell"><x-user-avatar :user="$staff" /><span><strong><a class="user-name-link" href="{{ $nameRoute }}">{{ $staffName }}</a></strong><small>{{ $staff->username ? '@'.$staff->username : $staff->email }}</small></span></div></td>
                <td class="user-role-text">{{ $roleLabel }}</td>
                <td>{{ number_format($staff->enforcer_attendances_count) }}</td>
                @foreach (['in' => [$timeInStart, $timeInEnd], 'out' => [$timeOutStart, $timeOutEnd]] as $action => $window)
                    <td>
                        <div>{{ $staff->attendance_restrictions_enabled ? \Illuminate\Support\Carbon::createFromFormat('H:i', $window[0])->format('h:i A').' - '.\Illuminate\Support\Carbon::createFromFormat('H:i', $window[1])->format('h:i A') : 'Not set' }}</div>
                    </td>
                @endforeach
                <td>{{ $staff->attendance_restrictions_enabled ? collect($days)->map(fn($day) => ucfirst(substr($day, 0, 3)))->join(', ') : 'Not set' }}</td>
                <td><span class="status-badge {{ $staff->attendance_restrictions_enabled ? '' : 'status-inactive' }}"><i></i>{{ $staff->attendance_restrictions_enabled ? 'Restricted' : 'Not set' }}</span></td>
                <td><div class="user-row-actions"><details class="user-actions-menu"><summary aria-label="Attendance actions" title="Actions">&#8942;</summary><div class="user-actions-dropdown">
                    <span class="sr-only">View details</span>
                    <button class="user-action action-edit" type="button" data-open-attendance-modal data-user-id="{{ $staff->id }}" data-name-encoded="{{ base64_encode($staffName) }}" data-action="{{ route('dashboard.attendance.restrictions.update', $staff) }}" data-enabled="{{ $staff->attendance_restrictions_enabled ? '1' : '0' }}" data-time-in-start="{{ $timeInStart }}" data-time-in-end="{{ $timeInEnd }}" data-time-out-start="{{ $timeOutStart }}" data-time-out-end="{{ $timeOutEnd }}" data-days='@json($days)'>Set restrictions</button>
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
                <div class="attendance-time-row">
                    <div class="attendance-time-label"><strong>Time In</strong><span>Allowed clock-in window</span></div>
                    <div class="attendance-restriction-times"><label><span>From</span><input type="time" name="attendance_time_in_start" required></label><span class="attendance-time-separator">to</span><label><span>Until</span><input type="time" name="attendance_time_in_end" required></label></div>
                </div>
                <div class="attendance-time-row">
                    <div class="attendance-time-label"><strong>Time Out</strong><span>Allowed clock-out window</span></div>
                    <div class="attendance-restriction-times"><label><span>From</span><input type="time" name="attendance_time_out_start" required></label><span class="attendance-time-separator">to</span><label><span>Until</span><input type="time" name="attendance_time_out_end" required></label></div>
                </div>
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
    let modalTrigger;
    document.querySelectorAll('[data-open-attendance-modal]').forEach(button => button.addEventListener('click', () => {
        modalTrigger = button;
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
    modal.addEventListener('close', () => {
        modalTrigger?.closest('details')?.setAttribute('open', '');
        modalTrigger?.focus();
    });
    const searchInput = document.querySelector('input[name="search"]');
    const clearSearch = document.querySelector('[data-clear-attendance-search]');
    searchInput.addEventListener('input', () => { clearSearch.hidden = searchInput.value === ''; });
    clearSearch.addEventListener('click', () => {
        searchInput.value = '';
        clearSearch.hidden = true;
        searchInput.focus();
        if (@json($search !== '')) searchInput.form.requestSubmit();
    });
})();
</script>
@endpush
