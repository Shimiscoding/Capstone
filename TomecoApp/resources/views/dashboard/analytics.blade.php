@extends('layouts.admin-dashboard')
@section('title', 'Analytics Reports')
@section('activePage', 'analytics')
@section('workspaceClass', 'analytics-reports')
@section('headerSearch')<span class="section-label">Reports &amp; insights</span>@endsection
@push('styles')
<link rel="stylesheet" href="{{ asset('css/analytics-reports.css') }}?v={{ filemtime(public_path('css/analytics-reports.css')) }}">
@endpush
@section('content')
<div class="page-head analytics-page-head">
    <div><p class="eyebrow">Traffic operations / Analytics</p><h1>Analytics Reports</h1><p class="page-description">See ticket trends, track unpaid fines, and plan enforcement activity.</p></div>
    <button type="button" class="analytics-button" id="analyticsPrint"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7z"/></svg>Print report</button>
</div>
<section class="analytics-filter-card" aria-label="Report filters">
<form method="GET" action="{{ route('dashboard.analytics') }}" id="analyticsFilters" novalidate aria-describedby="analyticsAutoFilterHint">
    <span class="sr-only" id="analyticsAutoFilterHint">Filters update the report automatically when changed. Custom dates update after both dates are valid.</span>
    <input type="hidden" name="view" value="{{ in_array(request('view'), ['overall', 'paid', 'unpaid']) ? request('view') : 'overall' }}">
    <input type="hidden" name="chart" value="{{ request('chart') === 'bar' ? 'bar' : 'line' }}">
    <div class="analytics-filter-fields">
        <label class="analytics-field" for="analyticsReportPeriod"><span>Report period</span><select id="analyticsReportPeriod" name="summary">
            @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'all' => 'All time', 'custom' => 'Custom dates'] as $value => $label)
                <option value="{{ $value }}" @selected(($filterErrors->has('date_from') || $filterErrors->has('date_to') ? 'custom' : $summaryRange) === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        @foreach (['date_from' => ['From', $dateFrom], 'date_to' => ['To', $dateTo]] as $name => [$label, $value])
        <label class="analytics-field" for="{{ $name }}"><span>{{ $label }}</span><input type="date" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" max="{{ $generatedAt->format('Y-m-d') }}" aria-invalid="{{ $filterErrors->has($name) ? 'true' : 'false' }}" @if($filterErrors->has($name)) aria-describedby="{{ $name }}-error" @endif>
        @if($filterErrors->has($name))<small class="analytics-field-error" id="{{ $name }}-error">{{ $filterErrors->first($name) }}</small>@endif</label>
        @endforeach
        <label class="analytics-field" for="analyticsStatus"><span>Payment status</span><select id="analyticsStatus" name="status"><option value="">All statuses</option><option value="unpaid" @selected($statusFilter === 'unpaid')>Unpaid</option><option value="paid" @selected($statusFilter === 'paid')>Paid</option></select></label>
        <label class="analytics-field" for="analyticsVehicle"><span>Vehicle type</span><select id="analyticsVehicle" name="vehicle"><option value="">All vehicles</option>@if($vehicleFilter !== '' && !$vehicleOptions->contains($vehicleFilter))<option value="{{ $vehicleFilter }}" selected>{{ $vehicleFilter }} (not recorded)</option>@endif @foreach($vehicleOptions as $option)<option value="{{ $option }}" @selected($vehicleFilter === $option)>{{ $option }}</option>@endforeach</select></label>
        <div class="analytics-filter-actions"><a class="analytics-button analytics-reset" href="{{ route('dashboard.analytics') }}">Reset</a></div>
    </div>
    <p id="analyticsFilterFeedback" class="analytics-filter-error" role="status" hidden></p>
    <noscript><button type="submit" class="analytics-button">Update report</button></noscript>
    @if($filterErrors->isNotEmpty())<p class="analytics-filter-error" role="alert" tabindex="-1">Some filters could not be applied. Correct the highlighted dates or choose another report period. {{ $filterErrors->first('summary') }} {{ $filterErrors->first('status') }} {{ $filterErrors->first('vehicle') }}</p>@endif
</form>
</section>
<div class="analytics-report-context"><div><strong>{{ $rangeStart->format('M d, Y') }} – {{ $rangeEnd->format('M d, Y') }}</strong><span>{{ ucfirst($summaryDescription) }}{{ $statusFilter ? ' · '.ucfirst($statusFilter) : ' · All statuses' }}{{ $vehicleFilter ? ' · '.$vehicleFilter : ' · All vehicles' }}</span></div><span>Generated {{ $generatedAt->format('M d, Y · h:i A') }} PHT</span></div>
<div class="analytics-kpi-grid" aria-label="Ticket summary">
    <article class="analytics-kpi is-total"><span>Tickets issued</span><strong>{{ number_format($summary['total']) }}</strong><small>@if($previousTotal === null)All recorded ticket history @elseif($ticketChange !== null){{ $ticketChange > 0 ? '+' : '' }}{{ number_format($ticketChange, 1) }}% vs previous period @elseif($summary['total']){{ number_format($summary['total']) }} tickets; none in previous period @else No tickets in either period @endif</small></article>
    <article class="analytics-kpi"><span>Paid tickets</span><strong>{{ number_format($summary['resolved']) }}</strong><small>{{ $settlementRate }}% of selected tickets</small></article>
    <article class="analytics-kpi"><span>Unpaid tickets</span><strong>{{ number_format($summary['pending']) }}</strong><small>Current status of selected tickets</small></article>
    <article class="analytics-kpi"><span>Outstanding fines</span><strong class="analytics-money">₱{{ number_format($outstandingFines, 2) }}</strong><small>Assessed on selected unpaid tickets</small></article>
</div>
@if($previousTotal !== null)<p class="analytics-comparison-note">Comparison: {{ $previousStart->format('M d, Y h:i A') }} – {{ $previousEnd->format('M d, Y h:i A') }} PHT · Equal elapsed duration with the same filters.</p>@endif
<div class="analytics-main-grid">
<section class="analytics-panel analytics-trend-panel">
    <div class="analytics-panel-heading"><div><h2>Ticket activity</h2><p>{{ ['hour' => 'Hourly', 'day' => 'Daily', 'month' => 'Monthly', 'year' => 'Yearly'][$granularity] }} ticket counts for the selected period</p></div><span class="analytics-label">{{ number_format($summary['total']) }} tickets</span></div>
    <div class="analytics-chart-toolbar"><label class="analytics-field" for="analyticsSeriesFilter"><span>Show</span><select id="analyticsSeriesFilter"><option value="overall">All tickets</option><option value="paid">Paid tickets</option><option value="unpaid">Unpaid tickets</option></select></label><label class="analytics-field" for="analyticsChartType"><span>Chart type</span><select id="analyticsChartType"><option value="line">Line chart</option><option value="bar">Bar chart</option></select></label></div>
    <div class="analytics-chart-frame"><canvas id="analyticsChart" role="img" aria-label="Ticket activity. The same counts are available in the activity data table below."></canvas></div>
    <p id="analyticsChartFeedback" class="analytics-inline-note" role="status" hidden>The chart could not load. Use the activity data table below or refresh to try again.</p>
    <noscript><p class="analytics-inline-note">Open View activity data below to read the ticket counts, or enable JavaScript to use the chart.</p></noscript>
    @if(!$summary['total'])<p class="analytics-inline-note">No tickets recorded during this period. Choose a longer range or reset the filters.</p>@endif
    <details class="analytics-data-disclosure"><summary>View activity data</summary><div class="analytics-table-scroll" tabindex="0" role="region" aria-label="Activity data"><table><thead><tr><th scope="col">Period (PHT)</th><th scope="col">Tickets issued</th><th scope="col">Paid</th><th scope="col">Unpaid</th></tr></thead><tbody>@foreach($dailyViolations as $bucket)<tr><td>{{ $bucket['date'] }}</td><td>{{ number_format($bucket['value']) }}</td><td>{{ number_format($bucket['paid']) }}</td><td>{{ number_format($bucket['unpaid']) }}</td></tr>@endforeach</tbody></table></div></details>
    <p class="analytics-inline-note">Grouped by issue date. Paid and unpaid reflect current ticket status, not payment dates.</p>
</section>
<section class="analytics-panel analytics-settlement-panel">
    <div class="analytics-panel-heading"><div><h2>Ticket settlement</h2><p>Current payment status</p></div></div>
    <div class="analytics-settlement-number"><strong>{{ $settlementRate }}<span>%</span></strong><p>of selected tickets are paid</p></div><div class="analytics-settlement-track" aria-hidden="true"><i style="width:{{ $settlementRate }}%"></i></div>
    <dl class="analytics-settlement-details"><div><dt><i class="analytics-dot is-paid" aria-hidden="true"></i>Paid</dt><dd>{{ number_format($summary['resolved']) }}</dd></div><div><dt><i class="analytics-dot" aria-hidden="true"></i>Unpaid</dt><dd>{{ number_format($summary['pending']) }}</dd></div><div><dt>Total assessed fines</dt><dd>₱{{ number_format($assessedFines, 2) }}</dd></div><div><dt>Enforcers issuing tickets</dt><dd>{{ number_format($activeEnforcers) }}</dd></div></dl>
    <p class="analytics-inline-note">Fine amounts are assessed values. Collections by payment date require payment records.</p>
</section>
</div>
<section class="analytics-panel analytics-violation-table">
    <div class="analytics-panel-heading"><div><h2>Most recorded violations</h2><p>Ranked by tickets issued in the selected period</p></div><span class="analytics-label">{{ number_format($violationTrends->count()) }} violation types</span></div>
    <div class="analytics-table-scroll" tabindex="0" role="region" aria-label="Violation rankings, scroll horizontally for more columns"><table><thead><tr><th scope="col">Violation type</th><th scope="col">Tickets issued</th><th scope="col">Share of tickets</th><th scope="col">Unpaid</th><th scope="col">Paid</th></tr></thead><tbody>
        @forelse($violationPage as $violation)
        @php($share = $periodViolationTotal ? round($violation->total / $periodViolationTotal * 100, 1) : 0)
        <tr><td>{{ $violation->violation_type ?: 'Unrecorded violation type' }}</td><td>{{ number_format($violation->total) }}</td><td><span class="analytics-share"><span>{{ $share }}%</span><span class="analytics-share-track" aria-hidden="true"><i style="width:{{ $share }}%"></i></span></span></td><td>{{ number_format($violation->total - $violation->resolved) }}</td><td>{{ number_format($violation->resolved) }}</td></tr>
        @empty<tr><td colspan="5"><div class="analytics-empty"><strong>No tickets recorded for these filters</strong><p>Choose a longer range or reset the filters to see violation rankings.</p></div></td></tr>@endforelse
    </tbody></table></div>
    <div class="analytics-table-footer"><span>All recorded violation types · {{ $violationPage->firstItem() ?? 0 }}–{{ $violationPage->lastItem() ?? 0 }} of {{ $violationPage->total() }}</span>@if($violationPage->hasPages())<nav aria-label="Violation ranking pages">@if($violationPage->previousPageUrl())<a class="analytics-button" href="{{ $violationPage->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $violationPage->currentPage() }} of {{ $violationPage->lastPage() }}</span>@if($violationPage->nextPageUrl())<a class="analytics-button" href="{{ $violationPage->nextPageUrl() }}">Next</a>@endif</nav>@endif</div>
    <p class="analytics-inline-note">A ticket can include several violation types, so shares may total more than 100%. {{ number_format($otherViolationTickets) }} tickets have no recorded violation group.</p>
    @if($violationPage->hasPages())<p class="analytics-print-note">This printout includes violation ranking page {{ $violationPage->currentPage() }} of {{ $violationPage->lastPage() }}.</p>@endif
</section>
<div class="analytics-breakdown-grid">
<section class="analytics-panel">
    <div class="analytics-panel-heading"><div><h2>Vehicle breakdown</h2><p>Most Common Vehicle Types</p></div></div>
    <div class="analytics-ranked-list">@forelse($vehicleTrends->take(5) as $item)<div class="analytics-ranked-item"><div><span>{{ $item->vehicle_type ?: 'Vehicle type not recorded' }}</span><strong>{{ number_format($item->total) }} <small>{{ $periodViolationTotal ? round($item->total / $periodViolationTotal * 100, 1) : 0 }}%</small></strong></div><div class="analytics-rank-track" aria-hidden="true"><i style="width:{{ $periodViolationTotal ? $item->total / $periodViolationTotal * 100 : 0 }}%"></i></div></div>@empty<div class="analytics-empty"><strong>No vehicle types recorded</strong><p>Choose a longer range or reset the filters.</p></div>@endforelse</div>
    @if($vehicleTrends->count() > 5)<p class="analytics-inline-note">Other vehicle types: {{ number_format($vehicleTrends->skip(5)->sum('total')) }} tickets.</p>@endif
</section>
<section class="analytics-panel">
    <div class="analytics-panel-heading"><div><h2>Recorded locations</h2><p>Top 5 locations by issued tickets</p></div></div>
    <div class="analytics-ranked-list">@forelse($locationTrends as $item)<div class="analytics-location-row"><span class="analytics-rank-number">{{ $loop->iteration }}</span><span>{{ $item->label }}</span><strong>{{ number_format($item->total) }}</strong></div>@empty<div class="analytics-empty"><strong>No location activity</strong><p>Choose a period with recorded tickets.</p></div>@endforelse</div>
    <p class="analytics-inline-note">Based on ticket location text. Different spellings may appear separately.</p>
</section>
</div>
<div class="analytics-breakdown-grid">
<section class="analytics-panel">
    <div class="analytics-panel-heading"><div><h2>Unpaid ticket aging</h2><p>Age today of unpaid tickets issued in this period</p></div></div>
    <div class="analytics-table-scroll" tabindex="0" role="region" aria-label="Unpaid ticket aging"><table><thead><tr><th scope="col">Time since issue</th><th scope="col">Tickets</th><th scope="col">Outstanding fines</th></tr></thead><tbody>@foreach($aging as $band)<tr><td>{{ $band['label'] }}</td><td>{{ number_format($band['total']) }}</td><td>₱{{ number_format($band['amount'], 2) }}</td></tr>@endforeach</tbody></table></div>
    <p class="analytics-inline-note">Age is measured in calendar days as of {{ $generatedAt->format('M d, Y') }} PHT. These bands do not indicate legal due dates.</p>
</section>
<section class="analytics-panel">
    <div class="analytics-panel-heading"><div><h2>Enforcer activity</h2><p>Top 5 enforcers by issued tickets</p></div><span class="analytics-label">{{ $activeEnforcers }} issuing</span></div>
    <div class="analytics-ranked-list">@forelse($enforcerTrends as $item)<div class="analytics-location-row"><span class="analytics-rank-number">{{ $loop->iteration }}</span><span>{{ $item->enforcer?->fullName ?: 'Enforcer not recorded' }}</span><strong>{{ number_format($item->total) }}</strong></div>@empty<div class="analytics-empty"><strong>No enforcer activity</strong><p>No tickets linked to enforcers in this period.</p></div>@endforelse</div>
    <p class="analytics-inline-note">Ticket volume describes recorded activity. Assignment and time worked affect these counts.</p>
</section>
</div>
<footer class="analytics-report-footnote">TOMECO · Traffic operations report · Counts reflect recorded tickets and their current status.</footer>
@endsection
@push('scripts')
<script type="application/json" id="analyticsChartData">@json($dailyViolations->values())</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/analytics-reports.js') }}?v={{ filemtime(public_path('js/analytics-reports.js')) }}" defer></script>
@endpush
