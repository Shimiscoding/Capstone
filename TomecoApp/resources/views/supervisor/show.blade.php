@extends('layouts.supervisor-dashboard')

@section('title', 'Supervisor Details')
@section('activePage', 'users-supervisors')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    <div class="page-head users-page-head">
        <div class="users-heading-copy">
            <h1><span class="management-subject">Supervisor</span> management <span class="users-heading-count">{{ $supervisor->enforcers->count() }}</span></h1>
            <p class="page-description">View the enforcers assigned to this supervisor.</p>
        </div>
    </div>

    @if (session('team_success'))
        <div class="success-message" role="status">{{ session('team_success') }}</div>
    @endif
    @if (session('team_error'))
        <div class="success-message" role="alert">{{ session('team_error') }}</div>
    @endif

    <div class="users-reference-toolbar supervisor-team-toolbar">
        <div class="users-toolbar-main"><div class="users-toolbar-actions">
            <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search assigned enforcers</span><input id="assignedEnforcerSearch" type="search" placeholder="Search"></label>
            <a class="toolbar-outline-button" href="{{ route('dashboard.users.supervisors') }}">Back</a>
            <button class="toolbar-outline-button" id="openSupervisorDetails" type="button">View details</button>
            <a class="toolbar-outline-button" href="{{ route('dashboard.users.edit', ['user' => $supervisor, 'from' => 'supervisors', 'return_to' => 'detail']) }}">Edit supervisor</a>
            <button class="toolbar-outline-button toolbar-add-user" id="openAddEnforcer" type="button">Add enforcer</button>
        </div>
        </div></div>

    <section class="table-card supervisor-team-full">
        <div class="table-scroll">
            <table class="users-table" id="assignedEnforcersTable">
                <thead>
                    <tr>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path d="M4 17c.6-4 2.6-6 6-6s5.4 2 6 6"/></svg>Full name</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6 2.8 8.3 7 6.8 8.5c1.1 2.3 2.4 3.6 4.7 4.7l1.5-1.5 4.2 2.3-.5 3"/></svg>Contact</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18s6-5.4 6-10a6 6 0 1 0-12 0c0 4.6 6 10 6 10z"/><circle cx="10" cy="8" r="2"/></svg>Location</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m7 10 2 2 4-4"/></svg>Status</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="2"/></svg>Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($supervisor->enforcers as $enforcer)
                        <tr data-assigned-enforcer="{{ \Illuminate\Support\Str::lower($enforcer->fullName.' '.$enforcer->username.' '.$enforcer->email) }}">
                            <td>
                                <div class="user-cell"><span
                                        class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?:'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@' . $enforcer->username : $enforcer->email }}</small></span>
                                </div>
                            </td>
                            <td>{{ $enforcer->phoneNumber ?: '—' }}<br><small>{{ $enforcer->email }}</small></td>
                            <td class="location-cell"
                                title="{{ collect([$enforcer->area, $enforcer->address])->filter()->join(', ') }}">
                                <strong>{{ $enforcer->area ?: '—' }}</strong><small
                                    class="location-truncate">{{ $enforcer->address ?: 'No address' }}</small>
                            </td>
                            <td><span class="status-badge"><i></i> Registered</span></td>
                            <td class="team-actions-column">
                                <div class="user-row-actions">
                                    <form method="POST" action="{{ route('dashboard.users.supervisors.enforcers.remove', [$supervisor, $enforcer]) }}"
                                        onsubmit="return confirm('Remove this enforcer from the supervisor?')">
                                        @csrf
                                        @method('DELETE')
                                        <details class="user-actions-menu"><summary aria-label="Actions" title="Actions">&#8942;</summary><div class="user-actions-dropdown"><a class="user-action action-edit" href="{{ route('dashboard.users.enforcers.show', $enforcer) }}">View details</a><button class="user-action action-delete" type="submit">Remove</button></div></details>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state"><strong>No assigned enforcers</strong><span>Assign an
                                    enforcer to this supervisor from the Enforcers section.</span></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>Showing {{ $supervisor->enforcers->count() }} of {{ $supervisor->enforcers->count() }}</span></div>
    </section>

    <dialog class="supervisor-details-modal available-enforcers-modal" id="addEnforcerModal">
        <div class="supervisor-modal-heading">
            <div>
                <p class="eyebrow">Available</p>
                <h2>Unassigned enforcers</h2>
            </div><button id="closeAddEnforcer" type="button" aria-label="Close add enforcer form">&times;</button>
        </div>
        <div class="enforcer-modal-search">
            <label for="adminEnforcerSearch">Search by name</label>
            <input id="adminEnforcerSearch" type="search" placeholder="Enter enforcer name" autocomplete="off">
        </div>
        <div class="table-scroll">
            <table class="users-table">
                <thead><tr><th>Enforcer</th><th>Contact</th><th>Location</th><th>Action</th></tr></thead>
                <tbody id="adminAvailableEnforcers">
                    @forelse($availableEnforcers as $enforcer)
                        <tr data-enforcer-name="{{ \Illuminate\Support\Str::lower($enforcer->fullName) }}">
                            <td><div class="user-cell"><span class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?: 'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@'.$enforcer->username : $enforcer->email }}</small></span></div></td>
                            <td>{{ $enforcer->phoneNumber ?: '—' }}</td>
                            <td class="location-cell"><strong>{{ $enforcer->area ?: '—' }}</strong><small class="location-truncate">{{ $enforcer->address ?: 'No address' }}</small></td>
                            <td><form method="POST" action="{{ route('dashboard.users.supervisors.enforcers.assign', [$supervisor, $enforcer]) }}">@csrf<button class="team-add-button" type="submit">Add to team</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state"><strong>No available enforcers</strong><span>All enforcers are currently assigned.</span></td></tr>
                    @endforelse
                    <tr class="enforcer-search-empty" hidden><td colspan="4" class="empty-state"><strong>No matching enforcers</strong><span>Try a different name.</span></td></tr>
                </tbody>
            </table>
        </div>
    </dialog>

    <dialog class="supervisor-details-modal" id="supervisorDetailsModal">
        <div class="supervisor-modal-heading">
            <div>
                <p class="eyebrow">Supervisor profile</p>
                <h2>{{ $supervisor->fullName }}</h2>
            </div><button id="closeSupervisorDetails" type="button" aria-label="Close supervisor details">&times;</button>
        </div>
        <dl class="user-detail-list supervisor-modal-details">
            <div>
                <dt>Username</dt>
                <dd>{{ $supervisor->username ? '@' . $supervisor->username : '—' }}</dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>{{ $supervisor->email ?: '—' }}</dd>
            </div>
            <div>
                <dt>Phone number</dt>
                <dd>{{ $supervisor->phoneNumber ?: '—' }}</dd>
            </div>
            <div>
                <dt>Area</dt>
                <dd>{{ $supervisor->area ?: '—' }}</dd>
            </div>
            <div>
                <dt>Address</dt>
                <dd>{{ $supervisor->address ?: '—' }}</dd>
            </div>
        </dl>
    </dialog>
@endsection

@push('scripts')
    <script>
        const supervisorDetailsModal = document.getElementById('supervisorDetailsModal');
        const addEnforcerModal = document.getElementById('addEnforcerModal');
        const adminEnforcerSearch = document.getElementById('adminEnforcerSearch');
        const adminEnforcerRows = [...document.querySelectorAll('#adminAvailableEnforcers tr[data-enforcer-name]')];
        const adminSearchEmpty = document.querySelector('#adminAvailableEnforcers .enforcer-search-empty');
        const assignedEnforcerSearch = document.getElementById('assignedEnforcerSearch');
        const assignedEnforcerRows = [...document.querySelectorAll('#assignedEnforcersTable tr[data-assigned-enforcer]')];
        assignedEnforcerSearch.addEventListener('input', () => {
            const query = assignedEnforcerSearch.value.trim().toLowerCase();
            assignedEnforcerRows.forEach(row => row.hidden = !row.dataset.assignedEnforcer.includes(query));
        });
        document.getElementById('openAddEnforcer').addEventListener('click', () => addEnforcerModal.showModal());
        document.getElementById('closeAddEnforcer').addEventListener('click', () => addEnforcerModal.close());
        addEnforcerModal.addEventListener('click', event => {
            if (event.target === addEnforcerModal) addEnforcerModal.close();
        });
        adminEnforcerSearch.addEventListener('input', () => {
            const query = adminEnforcerSearch.value.trim().toLowerCase();
            let visible = 0;
            adminEnforcerRows.forEach(row => {
                row.hidden = !row.dataset.enforcerName.includes(query);
                if (!row.hidden) visible++;
            });
            adminSearchEmpty.hidden = visible !== 0 || adminEnforcerRows.length === 0;
        });
        document.getElementById('openSupervisorDetails').addEventListener('click', () => supervisorDetailsModal
        .showModal());
        document.getElementById('closeSupervisorDetails').addEventListener('click', () => supervisorDetailsModal.close());
        supervisorDetailsModal.addEventListener('click', event => {
            if (event.target === supervisorDetailsModal) supervisorDetailsModal.close();
        });
    </script>
@endpush
