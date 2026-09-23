@extends(auth()->user()->isSupervisor() ? 'layouts.supervisor-dashboard' : 'layouts.admin-dashboard')

@section('title', 'Officer Location Monitoring')
@section('activePage', 'location-monitoring')
@section('workspaceClass', 'location-monitor-workspace')

@section('content')
<div id="locationMonitor" class="location-monitor"
    data-endpoint="{{ route('location-monitoring.officers') }}"
    data-map-key="{{ $googleMapsKey }}"
    data-center='@json($mapCenter)' data-thresholds='@json($thresholds)'>
    <div class="location-heading">
        <div><p class="eyebrow">Field operations</p><h1>Location Monitoring</h1><p>Monitor officer positions, duty status, and field activity in near real time.</p></div>
        <div id="liveConnection" class="connection-state is-connecting" role="status"><span></span>Connecting to live monitoring&hellip;</div>
    </div>

    <div class="location-summary" aria-label="Location summary">
        <article><div><span><i class="summary-indicator is-total"></i>Total officers</span><strong id="summaryOnDuty">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-active"></i>Active</span><strong id="summaryTeams">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-stale"></i>Delayed or Low Signal</span><strong id="summaryAlerts">&mdash;</strong></div></article>
        <article><div><span><i class="summary-indicator is-offline"></i>Offline</span><strong id="summaryOffline">&mdash;</strong></div></article>
    </div>

    <div class="location-toolbar">
        <label class="location-search"><span>Search officers</span><input id="officerSearch" type="search" placeholder="Name or personnel ID" autocomplete="off"></label>
        <label><span>Team</span><select id="teamFilter"><option value="">All Teams</option></select></label>
        <label><span>Status</span><select id="statusFilter"><option value="">All Statuses</option><option value="active">Active / Live</option><option value="delayed">Location Delayed</option><option value="offline">Offline</option><option value="off_duty">Off Duty</option></select></label>
        <label><span>Assigned Area</span><select id="areaFilter"><option value="">All Areas</option></select></label>
        <div class="location-toolbar-actions">
            <button id="refreshLocations" type="button"><span aria-hidden="true">&#8635;</span> Refresh</button>
            <button id="fitAllLocations" class="is-secondary" type="button"><span aria-hidden="true">&#8982;</span> Fit All</button>
        </div>
    </div>

    <div class="location-main">
        <aside class="officer-panel" aria-label="Officers">
            <div class="officer-panel-head"><div><h2>Field Officers</h2><span id="officerCount">Loading&hellip;</span></div><span class="officer-panel-live"><i></i> Live</span></div>
            <div id="officerList" class="officer-list"><div class="location-loading"><span></span><div><strong>Loading locations</strong><small>Retrieving current officer positions&hellip;</small></div></div>
        </aside>
        <section class="map-panel"><div id="mapStatus" class="map-status">Initializing Google Maps&hellip;</div><div id="officerMap" aria-label="Officer location map"></div></section>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/location-monitoring.js')
@endpush
