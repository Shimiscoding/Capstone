@once
    <link rel="stylesheet" href="{{ asset('css/dashboard-search.css') }}?v={{ filemtime(public_path('css/dashboard-search.css')) }}">
    <script src="{{ asset('js/dashboard-search.js') }}?v={{ filemtime(public_path('js/dashboard-search.js')) }}" defer></script>
@endonce
<form class="search dashboard-search" role="search" method="GET" action="{{ route('dashboard.search') }}" data-dashboard-search data-no-page-loader novalidate>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <label class="sr-only" for="dashboardSearchInput">Search dashboard</label>
    <input id="dashboardSearchInput" type="search" name="q" maxlength="160" placeholder="Search dashboard" autocomplete="off" aria-controls="dashboardSearchResults" data-dashboard-search-input>
    <button class="dashboard-search-clear" type="button" aria-label="Clear dashboard search" data-dashboard-search-clear hidden>&times;</button>
    <section class="dashboard-search-panel" id="dashboardSearchResults" aria-label="Dashboard search results" data-dashboard-search-panel hidden>
        <p class="dashboard-search-status" role="status" aria-live="polite" data-dashboard-search-status></p>
        <div data-dashboard-search-results></div>
        <button class="dashboard-search-retry page-button" type="button" data-dashboard-search-retry hidden>Retry search</button>
    </section>
</form>
