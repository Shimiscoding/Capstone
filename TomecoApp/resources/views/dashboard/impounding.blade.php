@extends('layouts.dashboard')

@section('title', 'Impounded Vehicles')
@section('activePage', 'impounding')
@section('headerSearch')
  <label class="search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input id="impound-search" type="search" placeholder="Search vehicles" aria-label="Search vehicles"></label>
@endsection

@section('content')
  <div class="page-head users-page-head"><div><p class="eyebrow">Vehicle management</p><h1>Impounded Vehicles</h1><p class="page-description">Track vehicles held in TOMECO facilities and monitor their release status.</p></div></div>
  <div class="table-card"><div class="table-scroll"><table class="users-table" id="impound-table">
    <thead><tr><th>Reference</th><th>Owner / Vehicle</th><th>Plate number</th><th>Violation</th><th>Date impounded</th><th>Location</th><th>Status</th></tr></thead>
    <tbody>
      @foreach ($vehicles as $vehicle)
        <tr><td><span class="badge-number">{{ $vehicle['reference'] }}</span></td><td><div class="user-cell"><span class="vehicle-icon">VEH</span><span><strong>{{ $vehicle['owner'] }}</strong><small>{{ $vehicle['vehicle'] }}</small></span></div></td><td><strong>{{ $vehicle['plate'] }}</strong></td><td>{{ $vehicle['violation'] }}</td><td>{{ $vehicle['date'] }}</td><td>{{ $vehicle['location'] }}</td><td><span class="impound-status status-{{ \Illuminate\Support\Str::slug($vehicle['status']) }}"><i></i>{{ $vehicle['status'] }}</span></td></tr>
      @endforeach
      <tr id="impound-empty" hidden><td colspan="7" class="empty-state"><strong>No vehicles found</strong><span>Try another reference, owner, plate number, or status.</span></td></tr>
    </tbody>
  </table></div><div class="table-footer"><span>Showing <strong id="impound-visible-count">{{ $vehicles->count() }}</strong> of {{ $vehicles->count() }} vehicles</span></div></div>
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
</script>
@endpush
