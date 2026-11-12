@extends('layouts.admin-dashboard')
@section('title', 'Violation Records')
@section('activePage', 'violation-records')
@section('workspaceClass', 'violation-records-page')
@section('headerSearch')<span class="section-label">Records workspace</span>@endsection
@push('styles')
<link rel="stylesheet" href="{{ asset('css/violation-records.css') }}?v={{ filemtime(public_path('css/violation-records.css')) }}">
@endpush
@section('content')
<div class="page-heading"><div><p class="eyebrow">Traffic operations / Records</p><h1>Violation Records</h1><p class="page-description">Find a ticket, review its details, and check payment status.</p></div><button class="button" id="printRecords" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7z"/></svg>Print list</button></div>
<section class="summary-strip" aria-label="Filtered ticket summary">
    <div><span>Issued tickets</span><strong>{{ number_format($recordSummary['total']) }}</strong></div>
    <div><span><i class="dot unpaid" aria-hidden="true"></i>Unpaid</span><strong>{{ number_format($recordSummary['unpaid']) }}</strong></div>
    <div><span><i class="dot paid" aria-hidden="true"></i>Paid</span><strong>{{ number_format($recordSummary['paid']) }}</strong></div>
    <div><span>Total assessed fines</span><strong>₱{{ number_format($recordSummary['fines'], 2) }}</strong></div>
</section>
<section class="records-panel" aria-label="Violation records">
    <form class="filter-toolbar" id="recordFilters" method="GET" action="{{ route('dashboard.violation-records') }}" role="search" novalidate aria-describedby="recordFilterHint">
        <span class="sr-only" id="recordFilterHint">Changing a filter automatically refreshes the list. Search runs after you stop typing or press Enter.</span>
        <label class="field search-field" for="recordSearch"><span>Search records</span><span class="search-input"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input type="search" id="recordSearch" name="search" value="{{ $search }}" placeholder="Ticket, driver, or plate" maxlength="160" autocomplete="off"><button class="clear-search" id="clearRecordSearch" type="button" aria-label="Clear search" @if($search === '') hidden @endif>×</button></span></label>
        <label class="field" for="violationFilter"><span>Violation type</span><select id="violationFilter" name="violation_type"><option value="">All violations</option>@foreach($violationTypes as $type)<option value="{{ $type }}" @selected($violationType === $type)>{{ $type }}</option>@endforeach</select></label>
        <label class="field" for="recordStatus"><span>Payment status</span><select id="recordStatus" name="status"><option value="">All statuses</option><option value="unpaid" @selected($statusFilter === 'unpaid')>Unpaid</option><option value="paid" @selected($statusFilter === 'paid')>Paid</option></select></label>
        <label class="field" for="recordPeriod"><span>Issued</span><select id="recordPeriod" name="period">@foreach(['month'=>'This month','week'=>'Last 7 days','today'=>'Today','all'=>'All time','custom'=>'Custom dates'] as $value=>$label)<option value="{{ $value }}" @selected(($filterErrors->has('date_from') || $filterErrors->has('date_to') ? 'custom' : $period) === $value)>{{ $label }}</option>@endforeach</select></label>
        <a class="button reset-button" href="{{ route('dashboard.violation-records') }}">Reset</a>
        <div class="records-date-fields" id="recordDates">
            @foreach(['date_from'=>['From',$dateFrom], 'date_to'=>['To',$dateTo]] as $name=>[$label,$value])
            <label class="field" for="record_{{ $name }}"><span>{{ $label }}</span><input type="date" id="record_{{ $name }}" name="{{ $name }}" value="{{ $value }}" aria-invalid="{{ $filterErrors->has($name) ? 'true' : 'false' }}" @if($filterErrors->has($name)) aria-describedby="{{ $name }}Error" @endif>@if($filterErrors->has($name))<small class="records-field-error" id="{{ $name }}Error">{{ $filterErrors->first($name) }}</small>@endif</label>
            @endforeach
        </div>
        @if($filterErrors->isNotEmpty())<p class="records-filter-error" role="alert" tabindex="-1">Some filters could not be applied. {{ $filterErrors->first() }}</p>@endif
        <p class="records-filter-error" id="recordFilterFeedback" role="status" hidden></p>
        <button class="button records-retry" id="retryRecords" type="button" hidden>Retry</button>
        <noscript><button class="button" type="submit">Update records</button></noscript>
    </form>
    <div class="table-heading"><div><strong>Issued tickets</strong><span>{{ number_format($violations->total()) }} {{ Str::plural('record', $violations->total()) }} found</span></div><label for="recordSort">Sort by <select id="recordSort" name="sort" form="recordFilters">@foreach(['newest'=>'Newest first','oldest'=>'Oldest first','fine'=>'Highest fine'] as $value=>$label)<option value="{{ $value }}" @selected($sortOrder === $value)>{{ $label }}</option>@endforeach</select></label></div>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Ticket table, scroll horizontally for all columns">
        <table><thead><tr><th scope="col">Ticket</th><th scope="col">Driver</th><th scope="col">Vehicle</th><th scope="col">Violation</th><th scope="col">Enforcer</th><th scope="col">Issued</th><th scope="col" class="amount-column">Fine / status</th><th scope="col" class="action-column">View Ticket</th></tr></thead><tbody>
        @forelse($violations as $violation)
            @php($issued = $violation->created_at?->copy()->setTimezone('Asia/Manila'))
            <tr>
                <td><a class="ticket-id" href="{{ route('dashboard.violation-records.show', $violation) }}" aria-label="View complete ticket {{ $violation->display_ticket_number }}">{{ $violation->display_ticket_number }}</a><small>{{ $issued?->format('h:i A') ?? '—' }}</small></td>
                <td><a class="driver-name" href="{{ route('dashboard.violation-records.motorist', $violation) }}">{{ $violation->full_name ?: 'Unknown driver' }}</a><small>{{ $violation->license_number ?: 'No permit recorded' }}</small></td>
                <td><span class="plate">{{ $violation->plate_number ?: '—' }}</span><small>{{ $violation->vehicle_type ?: 'Not specified' }}</small></td>
                <td><strong>{{ $violation->violation_type ?: 'Not recorded' }}</strong><small>{{ $violation->location ?: 'Location not recorded' }}</small></td>
                <td><strong>{{ $violation->enforcer_name ?: $violation->enforcer?->fullName ?: 'Not recorded' }}</strong><small>{{ filled($violation->enforcer_signature) ? 'Signature recorded' : 'Signature missing' }}</small></td>
                <td>{{ $issued?->format('M d, Y') ?? '—' }}</td>
                <td class="amount-column"><strong>₱{{ number_format($violation->fine_amount,2) }}</strong><br><span class="status-pill {{ $violation->status === 'paid' ? '' : 'is-unpaid' }}">{{ ucfirst($violation->status ?: 'unpaid') }}</span></td>
                <td class="action-column"><a class="button view-ticket" href="{{ route('dashboard.violation-records.show', $violation) }}" aria-label="View Ticket {{ $violation->display_ticket_number }}">View Ticket</a></td>
            </tr>
        @empty<tr><td colspan="8" class="empty-row"><strong>No violation records found</strong><p>Try a different search or use Reset to see this month’s tickets.</p></td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="table-footer"><span role="status">Showing {{ $violations->firstItem() ?? 0 }}–{{ $violations->lastItem() ?? 0 }} of {{ number_format($violations->total()) }} tickets</span><nav aria-label="Ticket pages">@if($violations->previousPageUrl())<a class="button page-button" href="{{ $violations->previousPageUrl() }}">Previous</a>@else<span class="button page-button is-disabled" aria-disabled="true">Previous</span>@endif<span>Page {{ $violations->currentPage() }} of {{ $violations->lastPage() }}</span>@if($violations->nextPageUrl())<a class="button page-button" href="{{ $violations->nextPageUrl() }}">Next</a>@else<span class="button page-button is-disabled" aria-disabled="true">Next</span>@endif</nav></div>
</section>
<div class="record-notes"><span>Dates in Asia/Manila · Amounts in PHP · Totals follow the current filters</span><span>Assessed fines are not cash collections</span></div>
<p class="records-print-note">Printed list: page {{ $violations->currentPage() }} of {{ $violations->lastPage() }} · Filters: {{ $period }}{{ $search ? ' · Search: '.$search : '' }}{{ $violationType ? ' · '.$violationType : '' }}{{ $statusFilter ? ' · '.$statusFilter : '' }}{{ $period === 'custom' ? ' · '.$dateFrom.' to '.$dateTo : '' }} · Generated {{ now('Asia/Manila')->format('M d, Y h:i A') }} PHT</p>
@endsection
@push('scripts')<script src="{{ asset('js/violation-records.js') }}?v={{ filemtime(public_path('js/violation-records.js')) }}" defer></script>@endpush
