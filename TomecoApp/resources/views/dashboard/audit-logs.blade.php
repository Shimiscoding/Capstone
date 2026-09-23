@extends('layouts.admin-dashboard')

@section('title', 'Audit Log')
@section('activePage', 'audit-logs')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    @php
        $displayValue = static function (mixed $value): string {
            if ($value === null || $value === '') return 'Empty';
            if (is_bool($value)) return $value ? 'Yes' : 'No';
            if (is_array($value)) return collect($value)->map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item))->join(', ');
            return (string) $value;
        };
        $fieldLabel = static fn (string $field): string => \Illuminate\Support\Str::headline($field);
        $recordType = static fn (string $type): string => match (class_basename($type)) {
            'User' => 'user account',
            'Violation' => 'violation ticket',
            'Setting' => 'setting',
            'EnforcerAttendance' => 'enforcer attendance',
            'SupervisorAttendance' => 'supervisor attendance',
            default => strtolower(\Illuminate\Support\Str::headline(class_basename($type))),
        };
    @endphp
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">Website activity</p>
            <h1>Audit Log</h1>
            <p class="page-description">Review important changes made through the website.</p>
        </div>
        <div class="user-count">{{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}</div>
    </div>

    <form class="user-table-filters users-reference-toolbar audit-log-toolbar" method="GET" action="{{ route('dashboard.audit-logs') }}">
        <div class="users-toolbar-main"><div class="users-toolbar-actions">
            <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search audit log</span><input type="search" name="search" value="{{ $search }}" placeholder="Search user or record"></label>
            <label class="audit-event-filter"><span class="sr-only">Filter by activity</span><select name="event" onchange="this.form.submit()"><option value="">All activity</option><option value="created" @selected($event === 'created')>Created</option><option value="updated" @selected($event === 'updated')>Updated</option><option value="deleted" @selected($event === 'deleted')>Deleted</option></select></label>
            <button class="toolbar-outline-button" type="submit">Search</button>
        </div></div>
    </form>

    <section class="table-card audit-log-card">
        <div class="table-scroll">
            <table class="users-table audit-log-table">
                <thead><tr><th>Date and time</th><th>Changed by</th><th>Activity</th><th>What they did</th><th>Record</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><time datetime="{{ $log->created_at->toIso8601String() }}"><strong>{{ $log->created_at->format('M d, Y') }}</strong><small>{{ $log->created_at->format('h:i:s A') }}</small></time></td>
                        <td><div class="audit-actor"><div class="audit-actor-name"><strong>{{ $log->actor_name ?: $log->user?->fullName ?: 'System' }}</strong>@if ($log->actor_role)<span class="role-badge">{{ $log->actor_role === \App\Models\User::ROLE_OFFICER ? 'Enforcer' : ucfirst($log->actor_role) }}</span>@endif</div><small>{{ $log->user?->email ?: 'Automated or archived account' }}</small></div></td>
                        <td><span class="audit-event is-{{ $log->event }}">{{ ucfirst($log->event) }}</span></td>
                        <td class="audit-action-cell">
                            <strong>{{ ucfirst($log->event) }} {{ $recordType($log->auditable_type) }}</strong>
                            @if ($log->event === 'updated' && count($log->changes ?? []) > 0)
                                <small>Changed {{ collect(array_keys($log->changes))->map($fieldLabel)->join(', ') }}</small>
                            @else
                                <small>{{ $log->subject_label }}</small>
                            @endif
                            @if (count($log->changes ?? []) > 0)
                                <details class="audit-change-details">
                                    <summary>View {{ count($log->changes) }} {{ \Illuminate\Support\Str::plural('change', count($log->changes)) }}</summary>
                                    <div class="audit-change-list">
                                        @foreach ($log->changes as $field => $change)
                                            <div>
                                                <strong>{{ $fieldLabel($field) }}</strong>
                                                @if ($field === 'signature')
                                                    <span class="audit-new-value">Signature {{ $log->event === 'deleted' ? 'removed' : 'recorded' }}</span>
                                                @else
                                                    <span class="audit-old-value">{{ $displayValue($change['old'] ?? null) }}</span>
                                                    <i aria-hidden="true">&rarr;</i>
                                                    <span class="audit-new-value">{{ $displayValue($change['new'] ?? null) }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td><strong>{{ $log->subject_label ?: class_basename($log->auditable_type).' #'.$log->auditable_id }}</strong><small>{{ class_basename($log->auditable_type) }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state"><strong>No website activity found</strong><span>Important website changes will appear here.</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="table-footer">
                <span>Showing {{ $logs->firstItem() }}&ndash;{{ $logs->lastItem() }} of {{ $logs->total() }}</span>
                <nav class="pagination-actions" aria-label="Audit log pagination">
                    @if ($logs->onFirstPage())
                        <span class="page-button is-disabled" aria-disabled="true">Previous</span>
                    @else
                        <a class="page-button" href="{{ $logs->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif

                    @if ($logs->hasMorePages())
                        <a class="page-button" href="{{ $logs->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span class="page-button is-disabled" aria-disabled="true">Next</span>
                    @endif
                </nav>
            </div>
        @endif
    </section>
@endsection
