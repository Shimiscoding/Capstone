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
            <p class="page-description">Review issued violations, motorist details, locations, and assessed fines.</p>
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
            <table class="users-table violation-records-table">
                <thead>
                    <tr>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="7" r="3"/><path d="M4 17c.6-4 2.6-6 6-6s5.4 2 6 6"/></svg>Full name</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="12" rx="2"/><path d="M6 8h3M6 11h7"/></svg>D/L Permit</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="5" width="14" height="10" rx="2"/><path d="M6 9h8M7 12h1M12 12h1"/></svg>Plate number</span></th>
                        <th>Vehicle type</th>
                        <th><abbr title="Official Receipt">OR</abbr> number</th>
                        <th><abbr title="Certificate of Registration">CR</abbr> number</th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 17 6v5c0 3.4-2.4 5.5-7 7-4.6-1.5-7-3.6-7-7V6zM10 6v5M10 14h.01"/></svg>Violation</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 18s6-5.4 6-10a6 6 0 1 0-12 0c0 4.6 6 10 6 10z"/><circle cx="10" cy="8" r="2"/></svg>Location</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M6 2v4M14 2v4M3 8h14"/></svg>Date issued</span></th>
                        <th><span class="users-column-heading"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M12.5 7.5H9a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H7.5M10 5v2.5M10 15.5V18"/></svg>Fine</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($violations as $violation)
                        @php
                            $motoristName = $violation->full_name;
                            $permitType = match ($violation->license_type) { 'professional' => 'Professional', 'non_professional' => 'Non-professional', 'student_permit' => 'Student permit', 'other' => 'Other', default => '—' };
                        @endphp
                        <tr class="violation-record-row" tabindex="0" role="link" aria-label="View ticket details for {{ $motoristName ?: 'unknown motorist' }}" data-record-url="{{ route('dashboard.violation-records.show', $violation) }}">
                            <td>
                                <div class="user-cell"><span
                                        class="table-avatar">{{ strtoupper(substr($motoristName ?: 'M', 0, 1)) }}</span><span><strong><a class="violation-name-button" href="{{ route('dashboard.violation-records.show', $violation) }}">{{ $motoristName ?: 'Unknown motorist' }}</a></strong></span>
                                </div>
                            </td>
                            <td><span class="violation-permit-cell"><span class="badge-number">{{ $violation->license_number ?: '—' }}</span><small>{{ $permitType }}</small></span></td>
                            <td><span class="badge-number">{{ $violation->plate_number }}</span></td>
                            <td>{{ $violation->vehicle_type ?: 'Not specified' }}</td>
                            <td><span class="badge-number">{{ $violation->or_number ?: '—' }}</span></td>
                            <td><span class="badge-number">{{ $violation->cr_number ?: '—' }}</span></td>
                            <td>{{ $violation->violation_type }}</td>
                            <td>{{ $violation->location ?: '—' }}</td>
                            <td>{{ $violation->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td><strong>&#8369;{{ number_format($violation->fine_amount, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state"><strong>No violation records found</strong><span>Try a different motorist, plate number, or violation.</span></td>
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

@push('scripts')
<script>
(() => {
    document.querySelectorAll('.violation-record-row').forEach(row => {
        row.addEventListener('click', event => {
            if (!event.target.closest('a')) window.location.assign(row.dataset.recordUrl);
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                window.location.assign(row.dataset.recordUrl);
            }
        });
    });
})();
</script>
@endpush
