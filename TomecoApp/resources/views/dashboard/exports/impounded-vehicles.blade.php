<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>table{border-collapse:collapse}th,td{border:1px solid #999;padding:6px 10px}th{background:#dc2626;color:#fff}.meta{background:#f3f4f6;font-weight:bold}</style></head><body>
<table>
  <tr><th colspan="9">TOMECO Impounded Vehicles Report</th></tr>
  <tr><td class="meta">Vehicle type</td><td colspan="3">{{ $filters['vehicle_type'] ?? 'All types' }}</td><td class="meta">Year range</td><td colspan="4">{{ $filters['year_from'] }}–{{ $filters['year_to'] }}</td></tr>
  <tr><th>Reference</th><th>Owner</th><th>Vehicle</th><th>Type</th><th>Plate Number</th><th>Violation</th><th>Date Impounded</th><th>Location</th><th>Status</th></tr>
  @forelse ($vehicles as $vehicle)
    <tr><td>{{ $vehicle->reference }}</td><td>{{ $vehicle->owner }}</td><td>{{ $vehicle->vehicle }}</td><td>{{ $vehicle->type }}</td><td>{{ $vehicle->plate }}</td><td>{{ $vehicle->violation }}</td><td>{{ $vehicle->impounded_at->format('Y-m-d') }}</td><td>{{ $vehicle->location }}</td><td>{{ $vehicle->status }}</td></tr>
  @empty
    <tr><td colspan="9">No impounded vehicles matched the selected filters.</td></tr>
  @endforelse
</table>
</body></html>
