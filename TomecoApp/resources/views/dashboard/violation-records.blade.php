@extends('layouts.admin-dashboard')

@section('title', 'Violation Records')
@section('activePage', 'violation-records')
@section('workspaceClass', 'workspace-full-width')
@section('headerSearch')
    <form class="search" method="GET" action="{{ route('dashboard.violation-records') }}" role="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
        </svg>
        <input type="search" name="search" value="{{ $search }}" placeholder="Search plate or violation">
    </form>
@endsection

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">Traffic violation records</p>
            <h1>Violation Records</h1>
            <p class="page-description">Review issued violations, driver details, locations, and assessed fines.</p>
        </div>
        <div class="user-count">{{ number_format($violations->total()) }}
            {{ \Illuminate\Support\Str::plural('record', $violations->total()) }}</div>
    </div>

    <div class="violation-stats">
        <article class="violation-stat is-primary">
            <p>Total violations</p><strong>{{ number_format($totalViolations) }}</strong><span>All recorded tickets</span>
        </article>
        <article class="violation-stat">
            <p>Issued this month</p><strong>{{ number_format($violationsThisMonth) }}</strong><span>New records during {{ now()->format('F') }}</span>
        </article>
    </div>
    <br>
    <form class="user-table-filters users-reference-toolbar" method="GET" action="{{ route('dashboard.violation-records') }}" role="search">
        <div class="users-toolbar-main">
            <div class="users-toolbar-actions">
                <label class="toolbar-search"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg><span class="sr-only">Search records</span><input type="search" name="search" value="{{ $search }}" placeholder="Search"></label>
                <button class="toolbar-text-button" type="button"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2 6.5 3.8v7.5L10 18l-6.5-4.7V5.8z"/><circle cx="10" cy="10" r="2.3"/></svg>Customize</button>
                <button class="toolbar-outline-button" type="button">Export</button>
            </div>
        </div>
        <div class="users-toolbar-filters">
            <details class="violation-filter-menu" @if ($violationType !== '' || $dateFrom !== '' || $dateTo !== '') open @endif>
                <summary class="add-filter-button">+&nbsp; Add filter</summary>
                <div class="violation-filter-panel">
                    <label><span>Violation type</span><select name="violation_type"><option value="">All violations</option>@foreach ($violationTypes as $type)<option value="{{ $type }}" @selected($violationType === $type)>{{ $type }}</option>@endforeach</select></label>
                    <label><span>Issued from</span><input type="date" name="date_from" value="{{ $dateFrom }}"></label>
                    <label><span>Issued to</span><input type="date" name="date_to" value="{{ $dateTo }}" min="{{ $dateFrom }}"></label>
                    <button class="violation-filter-apply" type="submit">Apply filters</button>
                </div>
            </details>
        </div>
    </form>

    <div class="table-card">
        <div class="table-scroll">
            <table class="users-table violation-records-table compact-records-table">
                <thead>
                    <tr>
                        <th>Ticket</th><th>Driver</th><th>Vehicle</th><th>Violation</th><th>Enforcer</th><th>Issued</th><th>Fine / status</th><th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($violations as $violation)
                        @php
                            $motoristName = $violation->full_name;
                            $permitType = match ($violation->license_type) { 'professional' => 'Professional', 'non_professional' => 'Non-professional', 'student_permit' => 'Student permit', 'other' => 'Other', default => '—' };
                        @endphp
                        <tr>
                            <td><a class="ticket-link" href="{{ route('dashboard.violation-records.show', $violation) }}">#{{ str_pad((string) $violation->id, 7, '0', STR_PAD_LEFT) }}</a><small>{{ $violation->created_at?->format('h:i A') }}</small></td>
                            <td><a class="motorist-name-link" href="{{ route('dashboard.violation-records.motorist', $violation) }}">{{ $motoristName ?: 'Unknown driver' }}</a><small>{{ $violation->license_number ?: 'No permit recorded' }}</small></td>
                            <td><strong class="badge-number">{{ $violation->plate_number ?: '—' }}</strong><small>{{ $violation->vehicle_type ?: 'Not specified' }}</small></td>
                            <td><span class="cell-wrap">{{ $violation->violation_type ?: '—' }}</span><small>{{ $violation->location ?: 'Location not recorded' }}</small></td>
                            <td><strong>{{ $violation->enforcer_name ?: 'Not recorded' }}</strong><small>{{ filled($violation->enforcer_signature) ? 'Signature recorded' : 'Signature missing' }}</small></td>
                            <td>{{ $violation->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td><strong>&#8369;{{ number_format($violation->fine_amount, 2) }}</strong><span class="status-badge {{ $violation->status === 'paid' ? '' : 'status-pending' }}"><i></i>{{ ucfirst($violation->status ?: 'unpaid') }}</span></td>
                            <td class="actions-column"><details class="record-actions"><summary aria-label="Actions for ticket {{ $violation->id }}" title="Ticket actions"><span aria-hidden="true">&#8942;</span></summary><div><a href="{{ route('dashboard.violation-records.show', $violation) }}">View complete ticket</a></div></details></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state"><strong>No violation records found</strong><span>Try a different plate number or violation.</span></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($violations->hasPages())
            <div class="table-footer"><span>Showing {{ $violations->firstItem() }}–{{ $violations->lastItem() }} of
                    {{ $violations->total() }}</span>
                <div class="pagination-actions">
                    @if ($violations->onFirstPage())
                    <span class="page-button is-disabled">Previous</span>@else<a class="page-button"
                            href="{{ $violations->previousPageUrl() }}">Previous</a>
                        @endif @if ($violations->hasMorePages())
                        <a class="page-button" href="{{ $violations->nextPageUrl() }}">Next</a>@else<span
                                class="page-button is-disabled">Next</span>
                        @endif
                </div>
            </div>
        @endif
    </div>

@endsection

@push('scripts')<script>document.addEventListener('click',event=>document.querySelectorAll('.record-actions[open]').forEach(menu=>{if(!menu.contains(event.target))menu.removeAttribute('open')}));</script>@endpush
