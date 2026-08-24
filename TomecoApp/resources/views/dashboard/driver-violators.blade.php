@extends('layouts.dashboard')

@section('title', 'Driver Violators')
@section('activePage', 'driver-violators')
@section('headerSearch')
  <form class="search" method="GET" action="{{ route('dashboard.driver-violators') }}" role="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input type="search" name="search" value="{{ $search }}" placeholder="Search plate or violation">
    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
  </form>
@endsection

@section('content')
  <div class="page-head users-page-head">
    <div>
      <p class="eyebrow">Traffic violation records</p>
      <h1>{{ $isDriverView ? 'My Violations' : 'Driver Violators' }}</h1>
      <p class="page-description">{{ $isDriverView ? 'Review violations issued to your account.' : 'Review drivers with recorded traffic violations and their payment status.' }}</p>
    </div>
    <div class="user-count">{{ number_format($violations->total()) }} {{ \Illuminate\Support\Str::plural('record', $violations->total()) }}</div>
  </div>

  <div class="payment-stats">
    <article class="payment-stat is-primary"><p>Total violations</p><strong>{{ number_format($totalViolations) }}</strong><span>All recorded tickets</span></article>
    <article class="payment-stat"><p>Outstanding</p><strong>{{ number_format($unpaidViolations) }}</strong><span class="trend-warn">Unpaid or pending</span></article>
    <article class="payment-stat"><p>Total fines issued</p><strong>&#8369;{{ number_format($totalFines, 2) }}</strong><span>Across all records</span></article>
  </div>
<br>
  <form class="user-table-filters" method="GET" action="{{ route('dashboard.driver-violators') }}" role="search">
    <label class="user-filter-search"><span>Search records</span><input type="search" name="search" value="{{ $search }}" placeholder="Plate number, violation, or registered driver"></label>
    <label><span>Payment status</span><select name="status"><option value="">All statuses</option><option value="unpaid" @selected($status === 'unpaid')>Unpaid</option><option value="pending" @selected($status === 'pending')>Pending</option><option value="paid" @selected($status === 'paid')>Paid</option></select></label>
    <button type="submit">Search</button><a href="{{ route('dashboard.driver-violators') }}">Clear filters</a>
  </form>

  <div class="table-card">
    <div class="table-scroll"><table class="users-table">
      <thead><tr><th>Driver</th><th>License number</th><th>Plate number</th><th>Violation</th><th>Location</th><th>Date issued</th><th>Fine</th><th>Status</th></tr></thead>
      <tbody>
        @forelse ($violations as $violation)
          @php $driverName = $violation->user?->fullName ?? $violation->driver_name; @endphp
          <tr>
            <td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($driverName ?: 'D', 0, 1)) }}</span><span><strong>{{ $driverName ?: 'Unknown driver' }}</strong></span></div></td>
            <td><span class="badge-number">{{ $violation->license_number ?: $violation->user?->driverLicense ?: '—' }}</span></td>
            <td><span class="badge-number">{{ $violation->plate_number }}</span></td>
            <td>{{ $violation->violation_type }}</td>
            <td>{{ $violation->location ?: '—' }}</td>
            <td>{{ $violation->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><strong>&#8369;{{ number_format($violation->fine_amount, 2) }}</strong></td>
            <td><span class="payment-status status-{{ strtolower($violation->status) }}">{{ ucfirst($violation->status) }}</span></td>
          </tr>
        @empty
          <tr><td colspan="8" class="empty-state"><strong>No violation records found</strong><span>Try changing the search or payment-status filter.</span></td></tr>
        @endforelse
      </tbody>
    </table></div>
    @if ($violations->hasPages())<div class="table-footer"><span>Showing {{ $violations->firstItem() }}–{{ $violations->lastItem() }} of {{ $violations->total() }}</span><div class="pagination-actions">@if ($violations->onFirstPage())<span class="page-button is-disabled">Previous</span>@else<a class="page-button" href="{{ $violations->previousPageUrl() }}">Previous</a>@endif @if ($violations->hasMorePages())<a class="page-button" href="{{ $violations->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled">Next</span>@endif</div></div>@endif
  </div>
@endsection
