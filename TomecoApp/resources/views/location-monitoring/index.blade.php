@extends(auth()->user()->isSupervisor() ? 'layouts.supervisor-dashboard' : 'layouts.admin-dashboard')

@section('title', 'Officer Location Monitoring')
@section('activePage', 'location-monitoring')
@section('workspaceClass', 'location-monitor-workspace')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/location-monitoring.css') }}?v={{ filemtime(public_path('css/location-monitoring.css')) }}">
@endpush

@section('content')
<div id="locationMonitor" class="location-monitor"
    data-endpoint="{{ route('location-monitoring.officers') }}"
    data-map-key="{{ $googleMapsKey }}"
    data-center='@json($mapCenter)' data-thresholds='@json($thresholds)'>
    <div class="location-heading">
        <div><p class="eyebrow">Field operations</p><h1>Location Monitoring</h1><p>Monitor officer positions, duty status, and field activity in near real time.</p></div>
        <div class="location-heading-actions">
            <div id="liveConnection" class="connection-state is-connecting" role="status"><span></span>Connecting&hellip;</div>
            <button id="refreshLocations" class="location-button" type="button"><span aria-hidden="true">&#8635;</span> Refresh</button>
            <button id="fitAllLocations" class="location-button" type="button"><span aria-hidden="true">&#8982;</span> Fit all</button>
        </div>
    </div>

    <div class="location-summary" aria-label="Location summary">
        <article><div><span><i class="summary-indicator is-total"></i>Total officers</span><strong id="summaryOnDuty">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-active"></i>Active</span><strong id="summaryTeams">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-stale"></i>Delayed</span><strong id="summaryAlerts">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-offline"></i>Offline</span><strong id="summaryOffline">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-off-duty"></i>Off duty</span><strong id="summaryOffDuty">&mdash;</strong></div></article>
    </div>

    <div class="location-main">
        <section class="map-panel" aria-label="Officer location map">
            <div id="officerMap"></div>
            <div id="mapStatus" class="map-status" role="status">Initializing map&hellip;</div>
        </section>
        <aside class="officer-panel" aria-label="Officers">
            <div class="officer-panel-head">
                <div><h2>Field officers</h2><span id="officerCount" role="status">Loading&hellip;</span></div>
                <button id="toggleOfficerPanel" class="location-icon-button" type="button" aria-controls="officerPanelBody" aria-expanded="true" aria-label="Collapse officer list" title="Collapse officer list"><span aria-hidden="true">&#8249;</span></button>
            </div>
            <div id="officerPanelBody" class="officer-panel-body">
                <div class="location-toolbar">
                    <div class="location-search">
                        <label class="sr-only" for="officerSearch">Search officers</label>
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
                        <input id="officerSearch" type="search" placeholder="Name or personnel ID" autocomplete="off">
                        <button id="clearOfficerSearch" class="location-icon-button" type="button" aria-label="Clear officer search" hidden>&times;</button>
                    </div>
                    <div class="location-filters">
                        <label><span>Team</span><select id="teamFilter"><option value="">All teams</option></select></label>
                        <label><span>Area</span><select id="areaFilter"><option value="">All areas</option></select></label>
                        <label><span>Status</span><select id="statusFilter"><option value="">All statuses</option><option value="active">Live</option><option value="delayed">Delayed</option><option value="offline">Offline</option><option value="off_duty">Off duty</option></select></label>
                    </div>
                </div>
                <div id="locationDataStatus" class="location-data-status" role="status" hidden></div>
                <div id="officerList" class="officer-list" aria-busy="true"><div class="location-loading"><span></span><div><strong>Loading locations</strong><small>Retrieving officer positions&hellip;</small></div></div></div>
                <div class="officer-panel-foot">Select an officer to see their latest position.</div>
            </div>
        </aside>
        <section id="selectedOfficer" class="selected-officer" aria-label="Selected officer" hidden>
            <div id="selectedOfficerContent"></div>
            <button id="closeOfficerDetails" class="location-icon-button" type="button" aria-label="Close officer details" title="Close officer details">&times;</button>
        </section>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/location-monitoring.js')
@endpush
