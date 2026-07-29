@extends('layouts.dashboard')

@section('title', 'Payments')
@section('activePage', 'payments')

@section('content')
  @if (session('success')) <div class="payment-alert is-success">{{ session('success') }}</div> @endif
  @if (session('error')) <div class="payment-alert is-error">{{ session('error') }}</div> @endif

  <div class="page-head payment-page-head">
    <div><p class="eyebrow">Secure online collection</p><h1>Payments</h1><p class="page-description">Pay violation fines through PayMongo or monitor recent transactions.</p></div>
    <div class="paymongo-badge"><span>Powered by</span><strong>PayMongo</strong></div>
  </div>

  <div class="payment-stats">
    <article class="payment-stat is-primary"><p>Total collected</p><strong>₱{{ number_format($totalCollected / 100, 2) }}</strong><span>Confirmed by PayMongo</span></article>
    <article class="payment-stat"><p>Successful payments</p><strong>{{ $successfulCount }}</strong><span>Completed transactions</span></article>
    <article class="payment-stat"><p>Pending payments</p><strong>{{ $pendingCount }}</strong><span class="trend-warn">₱{{ number_format($pendingAmount / 100, 2) }} awaiting confirmation</span></article>
    <article class="payment-stat"><p>Failed payments</p><strong>{{ $failedCount }}</strong><span class="trend-down">May be retried</span></article>
  </div>
 <br>
  <section class="dashboard-panel payable-panel">
    <div class="panel-heading"><div><h2>Unpaid violations</h2><p>Select a fine to continue to PayMongo's secure checkout.</p></div></div>
    @forelse ($unpaidViolations as $violation)
      <div class="payable-row">
        <div><strong>{{ $violation->violation_type }}</strong><small>{{ $violation->driver_name }} · {{ $violation->plate_number }} · Violation #{{ $violation->id }}</small></div>
        <span>₱{{ number_format($violation->fine_amount, 2) }}</span>
        <form method="POST" action="{{ route('payments.checkout', $violation) }}">@csrf<button class="pay-now-button" type="submit">Pay now</button></form>
      </div>
    @empty
      <div class="payment-empty">No unpaid violations found.</div>
    @endforelse
  </section>

  <div class="payment-layout">
    <section class="dashboard-panel collection-panel">
      <div class="panel-heading"><div><h2>Collection activity</h2><p>Confirmed totals for the last seven days</p></div><span class="chart-total">₱{{ number_format($dailyCollections->sum('amount') / 100, 2) }}</span></div>
      <div class="bar-chart" aria-label="Weekly collection chart">
        @foreach ($dailyCollections as $day)<div class="bar-column"><div class="bar-track"><span style="height: {{ max(3, round(($day['amount'] / $maxDaily) * 100)) }}%" title="₱{{ number_format($day['amount'] / 100, 2) }}"></span></div><small>{{ $day['label'] }}</small></div>@endforeach
      </div>
    </section>
    <aside class="dashboard-panel breakdown-panel"><div class="panel-heading"><div><h2>Payment security</h2><p>Hosted checkout protection</p></div></div><p class="security-copy">Payment details are entered directly on PayMongo. TOMECO only stores the transaction reference and status.</p></aside>
  </div>

  <section class="table-card payment-table-card">
    <div class="panel-heading"><div><h2>Recent transactions</h2><p>Latest PayMongo checkout activity</p></div></div>
    <div class="table-scroll"><table class="users-table"><thead><tr><th>Reference</th><th>Payer</th><th>Payment type</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      @forelse ($transactions as $transaction)
        <tr><td><span class="payment-reference">{{ $transaction->reference }}</span></td><td><strong>{{ $transaction->user?->fullName ?? $transaction->violation->driver_name }}</strong></td><td>{{ $transaction->violation->violation_type }}</td><td>{{ $transaction->created_at->format('M j, Y') }}</td><td class="payment-amount">₱{{ number_format($transaction->amount / 100, 2) }}</td><td><span class="payment-status status-{{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span></td></tr>
      @empty
        <tr><td colspan="6" class="payment-empty">No payment transactions yet.</td></tr>
      @endforelse
    </tbody></table></div>
  </section>
@endsection
