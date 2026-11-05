<dialog id="pageLoader" class="page-loader" aria-label="Loading page" aria-describedby="pageLoaderMessage" tabindex="-1" hidden>
    <div class="page-loader-content" role="status" aria-live="polite" aria-atomic="true">
        <img src="{{ asset('images/Tomeco_icon.jpg') }}" width="48" height="48" alt="">
        <strong>TOMECO</strong>
        <span class="page-loader-spinner" aria-hidden="true"></span>
        <span id="pageLoaderMessage">Loading page…</span>
    </div>
</dialog>
<script src="{{ asset('js/page-loader.js') }}?v={{ filemtime(public_path('js/page-loader.js')) }}" defer></script>
