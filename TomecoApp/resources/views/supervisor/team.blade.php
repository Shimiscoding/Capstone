@extends('layouts.supervisor-dashboard')

@section('title', 'My Team')
@section('activePage', 'supervisor-team')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    <div class="page-head users-page-head">
        <div class="users-heading-copy">
            <h1><span class="management-subject">My Team</span> management <span class="users-heading-count">{{ $teamMembers->count() }}</span></h1>
            <p class="page-description">Assign available enforcers to your team and manage current members.</p>
        </div>
    </div>
    @if (session('team_success'))
        <div class="success-message" role="status">{{ session('team_success') }}</div>
    @endif
    @if (session('team_error'))
        <div class="flash-alert is-error" role="alert">{{ session('team_error') }}</div>
    @endif

    <div class="users-reference-toolbar supervisor-team-toolbar">
        <div class="users-toolbar-main"><div class="users-toolbar-actions">
            <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search team members</span><input id="teamMemberSearch" type="search" placeholder="Search"></label>
            <button class="toolbar-outline-button toolbar-add-user" id="openAvailableEnforcers" type="button">Add enforcer</button>
        </div></div>
    </div>

        <section class="table-card supervisor-team-full">
            <div class="table-scroll">
                <table class="users-table" id="teamMembersTable">
                    <thead>
                        <tr>
                            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path d="M4 17c.6-4 2.6-6 6-6s5.4 2 6 6"/></svg>Full name</span></th>
                            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6 2.8 8.3 7 6.8 8.5c1.1 2.3 2.4 3.6 4.7 4.7l1.5-1.5 4.2 2.3-.5 3"/></svg>Contact</span></th>
                            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18s6-5.4 6-10a6 6 0 1 0-12 0c0 4.6 6 10 6 10z"/><circle cx="10" cy="8" r="2"/></svg>Location</span></th>
                            <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="2"/></svg>Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teamMembers as $enforcer)
                            <tr data-team-member="{{ \Illuminate\Support\Str::lower($enforcer->fullName.' '.$enforcer->username.' '.$enforcer->email) }}">
                                <td>
                                    <div class="user-cell"><span
                                            class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?:'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@' . $enforcer->username : $enforcer->email }}</small></span>
                                    </div>
                                </td>
                                <td>{{ $enforcer->phoneNumber ?: '—' }}</td>
                                <td class="location-cell"
                                    title="{{ collect([$enforcer->area, $enforcer->address])->filter()->join(', ') }}">
                                    <strong>{{ $enforcer->area ?: '—' }}</strong><small
                                        class="location-truncate">{{ $enforcer->address ?: 'No address' }}</small>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('supervisor.team.remove', $enforcer) }}"
                                        onsubmit="return confirm('Remove this enforcer from your team?')">@csrf
                                        @method('DELETE')<details class="user-actions-menu"><summary aria-label="Actions" title="Actions">&#8942;</summary><div class="user-actions-dropdown"><button class="user-action action-delete" type="submit">Remove</button></div></details>
                                    </form>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="4" class="empty-state"><strong>No team members yet</strong><span>Add an
                                        available enforcer from the list.</span></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer"><span>Showing {{ $teamMembers->count() }} of {{ $teamMembers->count() }}</span></div>
        </section>

    <dialog class="supervisor-details-modal available-enforcers-modal" id="availableEnforcersModal">
        <div class="supervisor-modal-heading">
            <div>
                <p class="eyebrow">Available</p>
                <h2>Unassigned enforcers</h2>
            </div><button id="closeAvailableEnforcers" type="button"
                aria-label="Close available enforcers">&times;</button>
        </div>
        <div class="enforcer-modal-search">
            <label for="teamEnforcerSearch">Search by name</label>
            <input id="teamEnforcerSearch" type="search" placeholder="Enter enforcer name" autocomplete="off">
        </div>
        <div class="table-scroll">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Enforcer</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="teamAvailableEnforcers">
                    @forelse($availableEnforcers as $enforcer)
                        <tr data-enforcer-name="{{ \Illuminate\Support\Str::lower($enforcer->fullName) }}">
                            <td>
                                <div class="user-cell"><span
                                        class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?:'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@' . $enforcer->username : $enforcer->email }}</small></span>
                                </div>
                            </td>
                            <td>{{ $enforcer->phoneNumber ?: '—' }}</td>
                            <td class="location-cell"
                                title="{{ collect([$enforcer->area, $enforcer->address])->filter()->join(', ') }}">
                                <strong>{{ $enforcer->area ?: '—' }}</strong><small
                                    class="location-truncate">{{ $enforcer->address ?: 'No address' }}</small>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('supervisor.team.assign', $enforcer) }}">@csrf<button
                                        class="team-add-button" type="submit">Add to team</button></form>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="4" class="empty-state"><strong>No available enforcers</strong><span>All
                                    enforcers are currently assigned.</span></td>
                        </tr>
                    @endforelse
                    <tr class="enforcer-search-empty" hidden><td colspan="4" class="empty-state"><strong>No matching enforcers</strong><span>Try a different name.</span></td></tr>
                </tbody>
            </table>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        const availableEnforcersModal = document.getElementById('availableEnforcersModal');
        const teamEnforcerSearch = document.getElementById('teamEnforcerSearch');
        const teamEnforcerRows = [...document.querySelectorAll('#teamAvailableEnforcers tr[data-enforcer-name]')];
        const teamSearchEmpty = document.querySelector('#teamAvailableEnforcers .enforcer-search-empty');
        const teamMemberSearch = document.getElementById('teamMemberSearch');
        const teamMemberRows = [...document.querySelectorAll('#teamMembersTable tr[data-team-member]')];
        teamMemberSearch.addEventListener('input', () => {
            const query = teamMemberSearch.value.trim().toLowerCase();
            teamMemberRows.forEach(row => row.hidden = !row.dataset.teamMember.includes(query));
        });
        document.getElementById('openAvailableEnforcers').addEventListener('click', () => availableEnforcersModal
        .showModal());
        document.getElementById('closeAvailableEnforcers').addEventListener('click', () => availableEnforcersModal.close());
        availableEnforcersModal.addEventListener('click', event => {
            if (event.target === availableEnforcersModal) availableEnforcersModal.close();
        });
        teamEnforcerSearch.addEventListener('input', () => {
            const query = teamEnforcerSearch.value.trim().toLowerCase();
            let visible = 0;
            teamEnforcerRows.forEach(row => {
                row.hidden = !row.dataset.enforcerName.includes(query);
                if (!row.hidden) visible++;
            });
            teamSearchEmpty.hidden = visible !== 0 || teamEnforcerRows.length === 0;
        });
    </script>
@endpush
