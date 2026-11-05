@extends('layouts.supervisor-dashboard')

@section('title', 'Supervisor Dashboard')
@section('activePage', 'dashboard')
@section('workspaceClass', 'supervisor-home')
@section('headerSearch')<span class="supervisor-topbar-label">Supervisor workspace</span>@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/supervisor-home.css') }}?v={{ filemtime(public_path('css/supervisor-home.css')) }}">
@endpush

@section('content')
    <header class="page-head supervisor-home-heading">
        <div>
            <p class="eyebrow">Team operations / Overview</p>
            <h1>Welcome, {{ $supervisor->firstName }}</h1>
            <p class="page-description">Your team, assignments, and coverage at a glance.</p>
        </div>
        <div class="supervisor-home-date"><strong>{{ $dashboardNow->format('l, F j, Y') }}</strong></div>
    </header>

    <section class="supervisor-home-stats" aria-label="Team summary">
        <a class="supervisor-home-stat is-primary" href="{{ route('supervisor.team') }}">
            <span>Assigned enforcers<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M20 21v-2a6 6 0 0 0-2-4"/></svg></span>
            <strong>{{ number_format($enforcerCount) }}</strong><small>Members under your supervision <span aria-hidden="true">↗</span></small>
        </a>
        <a class="supervisor-home-stat" href="{{ route('area-assignments.index') }}">
            <span>Areas covered<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/></svg></span>
            <strong>{{ number_format($areaCount) }}</strong><small>Distinct areas assigned to your team <span aria-hidden="true">↗</span></small>
        </a>
        <a class="supervisor-home-stat" href="{{ route('area-assignments.index', ['area' => '__unassigned']) }}">
            <span>Awaiting area assignment<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5 7-12a7 7 0 1 0-14 0c0 7 7 12 7 12Z"/><path d="M12 6v4M12 13v.5"/></svg></span>
            <strong>{{ number_format($unassignedAreaCount) }}</strong><small>{{ $unassignedAreaCount ? 'Review members without an assigned area' : 'No area assignments pending' }} <span aria-hidden="true">↗</span></small>
        </a>
    </section>

    <div class="supervisor-home-middle">
        <section class="supervisor-home-panel" aria-labelledby="coverageTitle">
            <div class="supervisor-home-panel-head"><div><h2 id="coverageTitle">Team coverage</h2><p>Enforcers by assigned area</p></div><a class="supervisor-home-link" href="{{ route('area-assignments.index') }}">Manage areas <span aria-hidden="true">→</span></a></div>
            @if ($coverageAreas->isNotEmpty())
                <ul class="supervisor-coverage-list">
                    @foreach ($coverageAreas as $area => $count)
                        <li><a href="{{ route('area-assignments.index', ['area' => $area]) }}"><span class="supervisor-coverage-name">{{ $area }}</span><span class="supervisor-coverage-track" aria-hidden="true"><span style="width: {{ round($count / max(1, $enforcerCount) * 100, 2) }}%"></span></span><strong>{{ $count }} <span>{{ \Illuminate\Support\Str::plural('enforcer', $count) }}</span></strong></a></li>
                    @endforeach
                </ul>
                <p class="supervisor-coverage-note">{{ $areaCount > 4 ? 'Showing the 4 areas with the most assigned enforcers.' : 'Based on current team assignments.' }} Assignment counts do not indicate live presence.</p>
            @else
                <div class="supervisor-home-empty"><span class="supervisor-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/></svg></span><strong>No areas assigned yet</strong><p>{{ $enforcerCount ? 'Assign an area to your enforcers to see team coverage here.' : 'Add enforcers to your team, then set their assigned areas.' }}</p><a class="supervisor-home-link" href="{{ route($enforcerCount ? 'area-assignments.index' : 'supervisor.team') }}">{{ $enforcerCount ? 'Assign areas' : 'Manage my team' }} <span aria-hidden="true">→</span></a></div>
            @endif
        </section>

        <section class="supervisor-home-panel supervisor-home-actions" aria-labelledby="actionsTitle">
            <div class="supervisor-home-panel-head"><div><h2 id="actionsTitle">Quick actions</h2><p>Continue your daily operations</p></div></div>
            <a href="{{ route('location-monitoring.index') }}"><span class="supervisor-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5 7-12a7 7 0 1 0-14 0c0 7 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg></span><span><strong>Location Monitoring</strong><small>View your team's reported positions</small></span><span aria-hidden="true">↗</span></a>
            <a href="{{ route('supervisor.enforcers.attendance.index') }}"><span class="supervisor-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 11h16m-12 5 3 3 5-5"/></svg></span><span><strong>Enforcer attendance</strong><small>Review your team's attendance history</small></span><span aria-hidden="true">↗</span></a>
            <a href="{{ route('supervisor.team') }}"><span class="supervisor-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M19 8v6M16 11h6"/></svg></span><span><strong>Manage my team</strong><small>Review and update your assigned members</small></span><span aria-hidden="true">↗</span></a>
        </section>
    </div>

    <section class="supervisor-home-panel supervisor-roster" aria-labelledby="rosterTitle">
        <div class="supervisor-home-panel-head"><div><h2 id="rosterTitle">Assigned enforcers <span class="supervisor-roster-count">{{ number_format($enforcerCount) }}</span></h2><p>Your current team directory</p></div><a class="supervisor-home-link" href="{{ route('supervisor.team') }}">Manage team <span aria-hidden="true">→</span></a></div>
        <div class="table-scroll" role="region" aria-label="Assigned enforcers directory" tabindex="0">
            <table class="users-table supervisor-roster-table">
                <caption class="sr-only">Enforcers assigned to your team, with contact information and assigned areas</caption>
                <thead><tr><th scope="col">Enforcer</th><th scope="col">Contact details</th><th scope="col">Assigned area</th><th scope="col">Account status</th><th scope="col"><span class="sr-only">Attendance details</span></th></tr></thead>
                <tbody>
                    @forelse ($enforcers as $enforcer)
                        @php($accountStatus = $enforcer->effectiveAccountStatus())
                        <tr>
                            <td><div class="user-cell"><x-user-avatar :user="$enforcer" /><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@'.$enforcer->username : 'No username' }}</small></span></div></td>
                            <td class="supervisor-roster-contact"><span>{{ $enforcer->email ?: 'No email recorded' }}</span><small>{{ $enforcer->phoneNumber ?: 'No phone recorded' }}</small></td>
                            <td class="supervisor-roster-area"><span class="{{ trim((string) $enforcer->area) === '' ? 'supervisor-area-pending' : '' }}">{{ trim((string) $enforcer->area) ?: 'Not assigned' }}</span><small>{{ $enforcer->address ?: 'No address recorded' }}</small></td>
                            <td><span class="supervisor-account-status {{ $accountStatus === 'active' ? 'is-active' : '' }}">{{ ucfirst($accountStatus) }}</span></td>
                            <td><a class="supervisor-home-link" href="{{ route('supervisor.enforcers.attendance', $enforcer) }}" aria-label="View attendance for {{ $enforcer->fullName }}">Attendance <span aria-hidden="true">→</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="supervisor-home-empty"><strong>No enforcers assigned yet</strong><p>Add an available enforcer from My Team to get started.</p><a class="supervisor-home-button" href="{{ route('supervisor.team') }}">Manage my team <span aria-hidden="true">→</span></a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>Showing {{ $enforcers->firstItem() ?? 0 }}–{{ $enforcers->lastItem() ?? 0 }} of {{ number_format($enforcerCount) }} enforcers</span><nav class="pagination-actions" aria-label="Team directory pages">@if ($enforcers->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Previous</span>@else<a class="page-button" href="{{ $enforcers->previousPageUrl() }}">Previous</a>@endif <span class="supervisor-page-number">Page {{ $enforcers->currentPage() }} of {{ max(1, $enforcers->lastPage()) }}</span> @if ($enforcers->hasMorePages())<a class="page-button" href="{{ $enforcers->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled" aria-disabled="true">Next</span>@endif</nav></div>
    </section>
@endsection
