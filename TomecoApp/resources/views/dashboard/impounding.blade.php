@extends('layouts.dashboard')

@section('title', 'Impounded Vehicles')
@section('activePage', 'impounding')
@section('headerSearch')
  <label class="search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input id="impound-search" type="search" placeholder="Search vehicles" aria-label="Search vehicles"></label>
@endsection

@section('content')
  @if (session('success')) <div class="payment-alert is-success">{{ session('success') }}</div> @endif
  @if (session('error') || $errors->any()) <div class="payment-alert is-error">{{ session('error') ?? $errors->first() }}</div> @endif
  @if (session('import_errors')) <div class="import-warning"><strong>Rows skipped:</strong><ul>@foreach (session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
  <div class="page-head users-page-head"><div><p class="eyebrow">Vehicle management</p><h1>Impounded Vehicles</h1><p class="page-description">Track vehicles held in TOMECO facilities and monitor their release status.</p></div><div class="impound-actions"><button class="import-excel-button" id="open-import-modal" type="button">Import Excel</button><button class="export-excel-button" id="open-export-modal" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/></svg>Export to Excel</button></div></div>
  <div class="table-card"><div class="table-scroll"><table class="users-table" id="impound-table">
    <thead><tr><th>Reference</th><th>Owner / Vehicle</th><th>Plate number</th><th>Violation</th><th>Date impounded</th><th>Location</th><th>Status</th></tr></thead>
    <tbody>
      @foreach ($vehicles as $vehicle)
        <tr><td><span class="badge-number">{{ $vehicle->reference }}</span></td><td><div class="user-cell"><span class="vehicle-icon">VEH</span><span><strong>{{ $vehicle->owner }}</strong><small>{{ $vehicle->vehicle }} · {{ $vehicle->type }}</small></span></div></td><td><strong>{{ $vehicle->plate }}</strong></td><td>{{ $vehicle->violation }}</td><td>{{ $vehicle->impounded_at->format('M j, Y') }}</td><td>{{ $vehicle->location }}</td><td><span class="impound-status status-{{ \Illuminate\Support\Str::slug($vehicle->status) }}"><i></i>{{ $vehicle->status }}</span></td></tr>
      @endforeach
      <tr id="impound-empty" hidden><td colspan="7" class="empty-state"><strong>No vehicles found</strong><span>Try another reference, owner, plate number, or status.</span></td></tr>
    </tbody>
  </table></div><div class="table-footer"><span>Showing <strong id="impound-visible-count">{{ $vehicles->count() }}</strong> of {{ $vehicles->count() }} vehicles</span></div></div>

  <dialog class="export-modal" id="export-modal" aria-labelledby="export-modal-title">
    <form method="GET" action="{{ route('dashboard.impounding.export') }}">
      <div class="export-modal-head"><div><p class="eyebrow">Report options</p><h2 id="export-modal-title">Export impounded vehicles</h2><p>Choose the vehicle type and year range to include.</p></div><button id="close-export-modal" type="button" aria-label="Close">×</button></div>
      <div class="export-modal-fields">
        <label><span>Vehicle type</span><select name="vehicle_type"><option value="">All vehicle types</option>@foreach ($vehicleTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label>
        <div class="year-range"><label><span>From year</span><select name="year_from" required>@foreach ($availableYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></label><label><span>To year</span><select name="year_to" required>@foreach ($availableYears as $year)<option value="{{ $year }}" @selected($loop->last)>{{ $year }}</option>@endforeach</select></label></div>
      </div>
      <div class="export-modal-actions"><button class="modal-cancel" id="cancel-export-modal" type="button">Cancel</button><button class="pay-now-button" type="submit">Download Excel</button></div>
    </form>
  </dialog>

  <dialog class="export-modal" id="import-modal" aria-labelledby="import-modal-title">
    <form method="POST" action="{{ route('dashboard.impounding.import') }}" enctype="multipart/form-data">@csrf
      <div class="export-modal-head"><div><p class="eyebrow">Spreadsheet upload</p><h2 id="import-modal-title">Import impounded vehicles</h2><p>Upload an XLSX, XLS, or CSV file up to 5 MB.</p></div><button id="close-import-modal" type="button" aria-label="Close">×</button></div>
      <div class="export-modal-fields"><label><span>Excel file</span><input class="excel-file-input" name="excel_file" type="file" accept=".xlsx,.xls,.csv" required></label><p class="import-help">Required columns: Reference, Owner, Vehicle, Type, Plate Number, Violation, Date Impounded, Location, Status. Existing references will be updated.</p></div>
      <div class="export-modal-actions"><button class="modal-cancel" id="cancel-import-modal" type="button">Cancel</button><button class="pay-now-button" type="submit">Import file</button></div>
    </form>
  </dialog>
@endsection

@push('scripts')
<script>
  const impoundSearch = document.getElementById('impound-search');
  const vehicleRows = [...document.querySelectorAll('#impound-table tbody tr:not(#impound-empty)')];
  const emptyRow = document.getElementById('impound-empty');
  const visibleCount = document.getElementById('impound-visible-count');
  impoundSearch.addEventListener('input', () => {
    const query = impoundSearch.value.trim().toLowerCase(); let matches = 0;
    vehicleRows.forEach(row => { const visible = row.textContent.toLowerCase().includes(query); row.hidden = !visible; if (visible) matches++; });
    emptyRow.hidden = matches !== 0; visibleCount.textContent = matches;
  });
  const exportModal = document.getElementById('export-modal');
  const closeExportModal = () => exportModal.close();
  document.getElementById('open-export-modal').addEventListener('click', () => exportModal.showModal());
  document.getElementById('close-export-modal').addEventListener('click', closeExportModal);
  document.getElementById('cancel-export-modal').addEventListener('click', closeExportModal);
  exportModal.addEventListener('click', event => { if (event.target === exportModal) closeExportModal(); });
  const importModal = document.getElementById('import-modal');
  const closeImportModal = () => importModal.close();
  document.getElementById('open-import-modal').addEventListener('click', () => importModal.showModal());
  document.getElementById('close-import-modal').addEventListener('click', closeImportModal);
  document.getElementById('cancel-import-modal').addEventListener('click', closeImportModal);
  importModal.addEventListener('click', event => { if (event.target === importModal) closeImportModal(); });
</script>
@endpush
