@extends('layouts.admin-dashboard')

@section('title', 'Home')
@section('activePage', 'dashboard')
@section('workspaceClass', 'home-dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/home-dashboard.css') }}?v={{ filemtime(public_path('css/home-dashboard.css')) }}">
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Traffic operations / Overview</p>
            <h1>Home</h1>
            <p class="page-description">Welcome, {{ auth()->user()->fullName }}. Here’s your operations overview.</p>
        </div>
        <div class="home-date"><strong>{{ $dashboardNow->format('l, F j, Y') }}</strong></div>
    </div>

    <section class="home-stats" aria-label="Operations summary">
        <a class="home-stat primary-stat" href="{{ route('dashboard.violation-records', ['period' => 'today']) }}">
            <span>Issued today<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18l-5-2-5 2zM10 8h4M10 12h4"/></svg></span>
            <strong>{{ number_format($todayTickets) }}</strong><small>Tickets recorded today</small>
        </a>
        <a class="home-stat" href="{{ route('dashboard.violation-records', ['period' => 'all', 'status' => 'unpaid']) }}">
            <span>Unpaid tickets<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <strong>{{ number_format($outstandingTickets) }}</strong><small>Across all issue dates</small>
        </a>
        <div class="home-stat">
            <span>Assessed fines today<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 21V3h5a5 5 0 0 1 0 10H7M4 7h15M4 10h15"/></svg></span>
            @php
                $fineParts = explode('.', number_format($todayAssessedFines, 2));
            @endphp
            <strong>&#8369;{{ $fineParts[0] }}<span class="cents">.{{ $fineParts[1] }}</span></strong><small>Fine amounts on today’s tickets</small>
        </div>
        <a class="home-stat" href="{{ route('dashboard.users.supervisors') }}">
            <span>Registered users<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="8" r="3"/><path d="M3 21v-2a7 7 0 0 1 14 0v2M17 4a3 3 0 0 1 0 6M20 21v-2a7 7 0 0 0-2-5"/></svg></span>
            <strong>{{ number_format($totalUsers) }}</strong><small>All staff accounts</small>
        </a>
    </section>

    <div class="home-middle">
        <section class="home-panel chart-panel" aria-labelledby="activityTitle">
            <div class="home-panel-head">
                <div><h2 id="activityTitle">Activity overview</h2><p>{{ $dashboardNow->copy()->subDays(6)->format('M j, Y') }}–{{ $dashboardNow->format('M j, Y') }} · Last 7 days</p></div>
                <label class="chart-select" for="activityMetric" hidden>Show<select id="activityMetric"><option value="violations">Issued tickets</option><option value="users">User registrations</option></select></label>
            </div>
            <div class="chart-summary" aria-live="polite"><strong id="chartTotal">{{ number_format($activityDays->sum('violations')) }}</strong><span id="chartCaption">tickets issued in the last 7 days</span><span class="chart-key"><i aria-hidden="true"></i><span id="chartLegend">Issued tickets</span></span></div>
            <div id="activityChart" class="home-chart" hidden></div>
            <p id="chartEmpty" class="chart-empty" role="status" hidden>No tickets issued in the last 7 days.</p>
            <details class="chart-data" open>
                <summary>View chart data</summary>
                <div class="chart-data-scroll"><table class="chart-data-table">
                    <caption class="sr-only">Daily activity for the last 7 days</caption>
                    <thead><tr><th scope="col">Date</th><th scope="col">Issued tickets</th><th scope="col">User registrations</th></tr></thead>
                    <tbody>
                        @foreach ($activityDays as $day)
                            <tr data-label="{{ $day['label'] }}" data-violations="{{ $day['violations'] }}" data-users="{{ $day['users'] }}"><th scope="row">{{ $day['date'] }}</th><td>{{ number_format($day['violations']) }}</td><td>{{ number_format($day['users']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
            </details>
        </section>
        <aside class="home-panel quick-links" aria-labelledby="quickTitle">
            <div class="home-panel-head"><div><h2 id="quickTitle">Quick actions</h2><p>Your frequently used tools</p></div></div>
            <a href="{{ route('dashboard.violation-records') }}"><span class="quick-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18l-5-2-5 2zM10 8h4M10 12h4"/></svg></span><span><strong>Review issued tickets</strong><small>Find a ticket or view its details</small></span><span aria-hidden="true">↗</span></a>
            <a href="{{ route('dashboard.analytics') }}"><span class="quick-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v16h16M8 16V9M13 16V5M18 16v-4"/></svg></span><span><strong>Open analytics</strong><small>Explore trends and reports</small></span><span aria-hidden="true">↗</span></a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('dashboard.users.supervisors') }}"><span class="quick-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span><strong>Manage users</strong><small>Review staff accounts</small></span><span aria-hidden="true">↗</span></a>
                <a href="{{ route('dashboard.attendance') }}"><span class="quick-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span><strong>Review attendance</strong><small>Check daily staff attendance</small></span><span aria-hidden="true">↗</span></a>
            @endif
        </aside>
    </div>

    <section class="home-panel recent-panel" aria-labelledby="recentTitle">
        <div class="home-panel-head"><div><h2 id="recentTitle">Recently issued tickets</h2><p>The {{ $recentViolations->count() }} latest {{ $recentViolations->count() === 1 ? 'ticket' : 'tickets' }} across all issue dates</p></div><a class="home-text-link" href="{{ route('dashboard.violation-records', ['period' => 'all']) }}">View all records <span aria-hidden="true">↗</span></a></div>
        <div class="table-scroll" role="region" tabindex="0" aria-label="Recently issued tickets, scroll for all columns">
            <table class="home-recent-table">
                <thead><tr><th scope="col">Ticket</th><th scope="col">Driver / Vehicle</th><th scope="col">Violation</th><th scope="col">Issued</th><th scope="col">Fine / status</th><th scope="col"><span class="sr-only">View Ticket</span></th></tr></thead>
                <tbody>
                    @forelse ($recentViolations as $violation)
                        @php
                            $ticketNumber = $violation->display_ticket_number;
                            $issued = $violation->created_at?->copy()->setTimezone('Asia/Manila');
                            $isPaid = $violation->status === 'paid';
                        @endphp
                        <tr>
                            <td><a class="ticket-id" href="{{ route('dashboard.violation-records.show', $violation) }}">{{ $ticketNumber }}</a></td>
                            <td><strong>{{ $violation->full_name ?: 'Not recorded' }}</strong><small>{{ $violation->plate_number ?: 'No plate recorded' }} · {{ $violation->vehicle_type ?: 'Vehicle not recorded' }}</small></td>
                            <td>{{ $violation->violation_type ?: 'Not recorded' }}<small>{{ $violation->location ?: 'Location not recorded' }}</small></td>
                            <td>{{ $issued?->format('M j, Y') ?: 'Not recorded' }}<small>{{ $issued?->format('h:i A') }}</small></td>
                            <td><strong>&#8369;{{ number_format($violation->fine_amount, 2) }}</strong><br><span class="status-pill {{ $isPaid ? '' : 'is-unpaid' }}">{{ $isPaid ? 'Paid' : ucfirst($violation->status ?: 'unpaid') }}</span></td>
                            <td><a class="button view-ticket" href="{{ route('dashboard.violation-records.show', $violation) }}" aria-label="View Ticket {{ $ticketNumber }}">View Ticket</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="home-empty"><strong>No tickets have been issued yet.</strong><p>New tickets will appear here after they are recorded.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <footer class="home-note"><span>Amounts in PHP</span><span>Assessed fines reflect ticket amounts.</span></footer>
@endsection

@push('scripts')
    <script src="{{ asset('js/home-dashboard.js') }}?v={{ filemtime(public_path('js/home-dashboard.js')) }}" defer></script>
@endpush
