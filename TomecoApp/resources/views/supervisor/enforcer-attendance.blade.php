@extends('layouts.supervisor-dashboard')

@section('title', 'Enforcer Attendance')
@section('activePage', 'enforcer-attendance')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    <div class="page-head users-page-head">
        <div class="users-heading-copy">
            <span class="sr-only">Enforcer Attendance</span>
            <h1><span class="management-subject">Enforcer</span> attendance <span class="users-heading-count">{{ number_format($enforcers->total()) }}</span></h1>
            <p class="page-description">Select an assigned enforcer to review their Time In and Time Out records.</p>
        </div>
    </div>

    <form class="user-table-filters users-reference-toolbar" method="GET" action="{{ route('supervisor.enforcers.attendance.index') }}" role="search">
        <div class="users-toolbar-main"><div class="users-toolbar-actions">
            <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search enforcers</span><input type="search" name="search" value="{{ $search }}" placeholder="Search"></label>
            <label class="toolbar-customize"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2 6.5 3.8v7.5L10 18l-6.5-4.7V5.8z"/><circle cx="10" cy="10" r="2.3"/></svg><span class="sr-only">Sort enforcers</span><select name="sort" onchange="this.form.submit()"><option value="latest" @selected($sort === 'latest')>Customize</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="name_asc" @selected($sort === 'name_asc')>Name A&ndash;Z</option><option value="name_desc" @selected($sort === 'name_desc')>Name Z&ndash;A</option></select></label>
            <button class="toolbar-outline-button" type="button">Export</button>
        </div></div>
    </form>

    <section class="table-card" aria-live="polite">
        <div class="table-scroll">
            <table class="users-table attendance-table">
                <thead><tr>
                    <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path d="M4 17c.6-4 2.6-6 6-6s5.4 2 6 6"/></svg>Full name</span></th>
                    <th>Attendance records</th>
                    <th>Latest Time In</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                @forelse($enforcers as $enforcer)
                    @php
                        $enforcerName = $enforcer->fullName ?: 'Unnamed enforcer';
                        $initials = collect(explode(' ', $enforcerName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
                    @endphp
                    <tr>
                        <td><div class="user-cell"><span class="table-avatar">{{ $initials ?: 'E' }}</span><span><strong><a class="user-name-link" href="{{ route('supervisor.enforcers.attendance', $enforcer) }}">{{ $enforcerName }}</a></strong><small>{{ $enforcer->username ? '@'.$enforcer->username : $enforcer->email }}</small></span></div></td>
                        <td>{{ number_format($enforcer->enforcer_attendances_count) }}</td>
                        <td>{{ $enforcer->enforcer_attendances_max_time_in ? \Illuminate\Support\Carbon::parse($enforcer->enforcer_attendances_max_time_in)->format('M d, Y h:i A') : '—' }}</td>
                        <td><div class="user-row-actions"><a class="user-action action-edit" href="{{ route('supervisor.enforcers.attendance', $enforcer) }}">View details</a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-state"><strong>{{ $search !== '' ? 'No enforcers found' : 'No assigned enforcers' }}</strong><span>{{ $search !== '' ? 'Try a different name.' : 'Add an enforcer from My Team to view attendance.' }}</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>Showing {{ $enforcers->firstItem() ?? 0 }}&ndash;{{ $enforcers->lastItem() ?? 0 }} of {{ $enforcers->total() }}</span><div class="pagination-actions">@if ($enforcers->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Previous</span>@else<a class="page-button" href="{{ $enforcers->previousPageUrl() }}">Previous</a>@endif @if ($enforcers->hasMorePages())<a class="page-button" href="{{ $enforcers->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled" aria-disabled="true">Next</span>@endif</div></div>
    </section>
@endsection
