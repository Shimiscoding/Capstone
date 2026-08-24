@extends('layouts.dashboard')

@php
    $sectionTitle = match ($userSection ?? null) {
        'supervisors' => 'Supervisors',
        'enforcers' => 'Enforcers',
        'admins' => 'Admins',
        default => 'Users',
    };
    $sectionRoute = match ($userSection ?? null) {
        'supervisors' => route('dashboard.users.supervisors'),
        'enforcers' => route('dashboard.users.enforcers'),
        'admins' => route('dashboard.users.admins'),
        default => route('dashboard.users'),
    };
    $managementSubject = match ($userSection ?? null) {
        'supervisors' => 'Supervisor',
        'enforcers' => 'Enforcer',
        'admins' => 'Admin',
        default => 'User',
    };
    $managementDescription = match ($userSection ?? null) {
        'supervisors' => 'Manage your supervisors and their account permissions here.',
        'enforcers' => 'Manage your enforcers and their account permissions here.',
        'admins' => 'Manage your administrators and their account permissions here.',
        default => 'Manage your team members and their account permissions here.',
    };
    $shownFilters = array_intersect((array) request()->query('filters', []), ['role', 'joined']);
    $showRoleFilter = $role !== '' || in_array('role', $shownFilters, true);
    $showJoinedFilter = $joinedFrom !== '' || $joinedTo !== '' || in_array('joined', $shownFilters, true);
@endphp
@section('title', $sectionTitle)
@section('activePage', $userSection ?? null ? 'users-' . $userSection : 'users')
@section('workspaceClass', 'workspace-full-width')
@section('headerSearch')
    <form class="search" method="GET" action="{{ $sectionRoute }}" role="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search {{ strtolower($sectionTitle) }}">
        <input type="hidden" name="sort" value="{{ $sort }}">
    </form>
@endsection

@section('content')
    <div class="page-head users-page-head">
        <div class="users-heading-copy"><h1><span class="management-subject">{{ $managementSubject }}</span> management <span class="users-heading-count">{{ number_format($users->total()) }}</span></h1><p class="page-description">{{ $managementDescription }}</p></div>
    </div>
    @if (session('success'))<div class="success-message" role="status">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="success-message" role="alert">{{ session('error') }}</div>@endif

    <form id="userTableFilters" class="user-table-filters users-reference-toolbar" method="GET" action="{{ $sectionRoute }}" role="search">
        <div class="users-toolbar-main">
            <div class="users-toolbar-actions">
                <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search users</span><input type="search" name="search" value="{{ $search }}" placeholder="Search"></label>
                <label class="toolbar-customize"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2 6.5 3.8v7.5L10 18l-6.5-4.7V5.8z"/><circle cx="10" cy="10" r="2.3"/></svg><span class="sr-only">Sort users</span><select name="sort" onchange="this.form.submit()"><option value="latest" @selected($sort === 'latest')>Customize</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="name_asc" @selected($sort === 'name_asc')>Name A–Z</option><option value="name_desc" @selected($sort === 'name_desc')>Name Z–A</option></select></label>
                <button class="toolbar-outline-button" type="button">Export</button>
                <a class="toolbar-outline-button toolbar-add-user" href="{{ match ($userSection ?? null) {'supervisors' => route('dashboard.users.supervisors.create'),'enforcers' => route('dashboard.users.enforcers.create'),'admins' => route('dashboard.users.admins.create'),default => route('dashboard.users.create')} }}">Add User<svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></a>
            </div>
        </div>
        <div class="users-toolbar-filters">
            @if (!isset($userSection))
                <div class="active-user-filter role-filter-wrap user-dynamic-filter" data-user-filter="role" @if (!$showRoleFilter) hidden @endif><input type="hidden" name="filters[]" value="role" data-filter-state="role" @disabled(!$showRoleFilter)><label class="users-filter-chip"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="7" cy="7" r="3"/><path d="M2.5 16c.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5M13 8h4M15 6v4"/></svg><span class="sr-only">Filter by role</span><select name="role" onchange="this.form.submit()"><option value="" @selected($role === '')>Role</option><option value="officer" @selected($role === 'officer')>Enforcers</option><option value="supervisor" @selected($role === 'supervisor')>Supervisors</option><option value="admin" @selected($role === 'admin')>Admins</option></select><svg class="filter-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></label><button class="remove-user-filter" type="button" data-remove-user-filter="role" aria-label="Remove role filter">&times;</button></div>
            @endif
            <div class="active-user-filter user-dynamic-filter" data-user-filter="joined" @if (!$showJoinedFilter) hidden @endif><input type="hidden" name="filters[]" value="joined" data-filter-state="joined" @disabled(!$showJoinedFilter)><details class="violation-filter-menu joined-date-filter">
                    <summary class="users-filter-summary"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M6 2v4M14 2v4M3 8h14"/></svg>Joined date<svg class="filter-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary>
                    <div class="violation-filter-panel">
                        <label><span>Joined from</span><input type="date" name="joined_from" value="{{ $joinedFrom }}"></label>
                        <label><span>Joined to</span><input type="date" name="joined_to" value="{{ $joinedTo }}" min="{{ $joinedFrom }}"></label>
                        <button class="violation-filter-apply" type="submit">Apply filters</button>
                    </div>
                </details><button class="remove-user-filter" type="button" data-remove-user-filter="joined" aria-label="Remove joined date filter">&times;</button></div>
            <details class="add-filter-picker">
                <summary class="add-filter-button">+&nbsp; Add filter</summary>
                <div class="add-filter-options">
                    @if (!isset($userSection))<button type="button" data-add-user-filter="role"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="7" cy="7" r="3"/><path d="M2.5 16c.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5M13 8h4M15 6v4"/></svg>Role</button>@endif
                    <button type="button" data-add-user-filter="joined"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M6 2v4M14 2v4M3 8h14"/></svg>Joined date</button>
                </div>
            </details>
        </div>
    </form>

    <div class="table-card" aria-live="polite"><div class="table-scroll"><table class="users-table">
        <thead><tr>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path d="M4 17c.6-4 2.6-6 6-6s5.4 2 6 6"/></svg>Full name</span></th>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18s6-5.4 6-10a6 6 0 1 0-12 0c0 4.6 6 10 6 10z"/><circle cx="10" cy="8" r="2"/></svg>Location</span></th>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6 2.8 8.3 7 6.8 8.5c1.1 2.3 2.4 3.6 4.7 4.7l1.5-1.5 4.2 2.3-.5 3c-.2.8-.8 1.2-1.6 1.2C8 17.7 2.3 12 1.8 4.9c0-.8.4-1.4 1.2-1.6z"/></svg>Phone number</span></th>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="7" cy="7" r="3"/><path d="M2.5 16c.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5M13 8h4M15 6v4"/></svg>Role</span></th>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M6 2v4M14 2v4M3 8h14"/></svg>Joined</span></th>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m7 10 2 2 4-4"/></svg>Status</span></th>
            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="2"/></svg>Actions</span></th>
        </tr></thead>
        <tbody>
        @forelse ($users as $listedUser)
            @php $listedName = $listedUser->fullName ?: 'Unnamed user'; $listedInitials = collect(explode(' ', $listedName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join(''); @endphp
            <tr>
                <td><div class="user-cell"><span class="table-avatar">{{ $listedInitials ?: 'U' }}</span><span><strong>@if (in_array($userSection ?? null, ['supervisors', 'enforcers', 'admins'], true))<a class="user-name-link" href="{{ match ($userSection) {'supervisors' => route('dashboard.users.supervisors.show', $listedUser),'enforcers' => route('dashboard.users.enforcers.show', $listedUser),'admins' => route('dashboard.users.admins.show', $listedUser)} }}">{{ $listedName }}</a>@else{{ $listedName }}@endif</strong></span></div></td>
                <td class="location-cell"><strong>{{ $listedUser->barangay ?: '—' }}</strong><small class="location-truncate">{{ collect([$listedUser->area, $listedUser->address])->filter()->join(', ') ?: 'No address' }}</small></td>
                <td>{{ $listedUser->phoneNumber ?: '—' }}</td><td class="user-role-text">{{ $listedUser->isOfficer() ? 'Enforcer' : ucfirst($listedUser->role) }}</td><td>{{ $listedUser->created_at?->format('M d, Y') ?? '—' }}</td>@php $accountStatus = $listedUser->effectiveAccountStatus(); @endphp<td><span class="status-badge status-{{ $accountStatus }}" title="{{ match ($accountStatus) {'active' => 'Fully verified and able to log in.','pending' => 'Waiting for email or phone verification.','inactive' => 'Deactivated or unused for a long time.','suspended' => 'Temporarily blocked.','banned' => 'Permanently blocked.'} }}"><i></i> {{ ucfirst($accountStatus) }}</span></td>
                <td><div class="user-row-actions"><details class="user-actions-menu"><summary aria-label="Actions for {{ $listedName }}" title="Actions">&#8942;</summary><div class="user-actions-dropdown"><a class="user-action action-edit" href="{{ route('dashboard.users.edit', ['user' => $listedUser, 'from' => $userSection ?? 'users']) }}">Edit</a><form method="POST" action="{{ route('dashboard.users.destroy', $listedUser) }}" onsubmit="return confirm('Delete this user? This cannot be undone.')">@csrf @method('DELETE') @if (isset($userSection))<input type="hidden" name="user_section" value="{{ $userSection }}">@endif<button class="user-action action-delete" type="submit">Delete</button></form></div></details></div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state"><strong>{{ $search !== '' ? 'No users found' : 'No registered users yet' }}</strong><span>{{ $search !== '' ? 'Try a different name.' : 'No registered accounts yet.' }}</span></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="table-footer"><span>Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</span><div class="pagination-actions">@if ($users->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Previous</span>@else<a class="page-button" href="{{ $users->previousPageUrl() }}">Previous</a>@endif @if ($users->hasMorePages())<a class="page-button" href="{{ $users->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled" aria-disabled="true">Next</span>@endif</div></div>
    </div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-add-user-filter]').forEach(button => {
    button.addEventListener('click', () => {
        const filter = document.querySelector(`[data-user-filter="${button.dataset.addUserFilter}"]`);
        if (!filter) return;
        filter.hidden = false;
        const filterState = filter.querySelector('[data-filter-state]');
        if (filterState) filterState.disabled = false;
        button.closest('.add-filter-picker')?.removeAttribute('open');
        filter.querySelector('select, input')?.focus();
        filter.querySelector('details')?.setAttribute('open', '');
    });
});
document.querySelectorAll('[data-remove-user-filter]').forEach(button => {
    button.addEventListener('click', () => {
        if (button.dataset.removeUserFilter === 'role') {
            const roleSelect = document.querySelector('[name="role"]');
            if (roleSelect) roleSelect.value = '';
        }
        if (button.dataset.removeUserFilter === 'joined') {
            const joinedFrom = document.querySelector('[name="joined_from"]');
            const joinedTo = document.querySelector('[name="joined_to"]');
            if (joinedFrom) joinedFrom.value = '';
            if (joinedTo) joinedTo.value = '';
        }
        const filterState = button.closest('[data-user-filter]')?.querySelector('[data-filter-state]');
        if (filterState) filterState.disabled = true;
        document.getElementById('userTableFilters')?.requestSubmit();
    });
});
</script>
@endpush
