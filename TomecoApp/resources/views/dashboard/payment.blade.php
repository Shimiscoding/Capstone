@extends('layouts.dashboard')

@section('title', 'Payment Overview')
@section('activePage', 'payments')

@section('headerSearch')
  <label class="search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" placeholder="Search payments" aria-label="Search payments"></label>
@endsection

@section('content')
  @php
    $transactions = [
      ['reference' => 'PAY-2026-0148', 'payer' => 'Juan Dela Cruz', 'type' => 'Traffic violation', 'date' => 'Jul 14, 2026', 'amount' => '₱1,500.00', 'status' => 'Paid'],
      ['reference' => 'PAY-2026-0147', 'payer' => 'Maria Santos', 'type' => 'Parking violation', 'date' => 'Jul 14, 2026', 'amount' => '₱500.00', 'status' => 'Pending'],
      ['reference' => 'PAY-2026-0146', 'payer' => 'Carlo Reyes', 'type' => 'Traffic violation', 'date' => 'Jul 13, 2026', 'amount' => '₱2,000.00', 'status' => 'Paid'],
      ['reference' => 'PAY-2026-0145', 'payer' => 'Angela Lim', 'type' => 'Impound fee', 'date' => 'Jul 13, 2026', 'amount' => '₱3,500.00', 'status' => 'Failed'],
      ['reference' => 'PAY-2026-0144', 'payer' => 'Miguel Garcia', 'type' => 'Parking violation', 'date' => 'Jul 12, 2026', 'amount' => '₱750.00', 'status' => 'Paid'],
    ];
  @endphp
  <div class="page-head payment-page-head"><div><p class="eyebrow">Financial overview</p><h1>Payments</h1><p class="page-description">Monitor collections and recent payment activity.</p></div><div class="payment-period"><span>Period</span><strong>July 2026</strong></div></div>
  <div class="payment-stats">
    <article class="payment-stat is-primary"><p>Total collected</p><strong>₱128,750.00</strong><span class="trend-up">↑ 12.4% from last month</span></article>
    <article class="payment-stat"><p>Successful payments</p><strong>86</strong><span>91.5% completion rate</span></article>
    <article class="payment-stat"><p>Pending payments</p><strong>8</strong><span class="trend-warn">₱12,250.00 awaiting payment</span></article>
    <article class="payment-stat"><p>Failed payments</p><strong>3</strong><span class="trend-down">Needs attention</span></article>
  </div>
  <div class="payment-layout">
    <section class="dashboard-panel collection-panel"><div class="panel-heading"><div><h2>Collection activity</h2><p>Payment totals for the last seven days</p></div><span class="chart-total">₱42,500</span></div><div class="bar-chart" aria-label="Static weekly collection chart">@foreach ([42,67,51,78,59,88,72] as $height)<div class="bar-column"><div class="bar-track"><span style="height: {{ $height }}%"></span></div><small>{{ ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$loop->index] }}</small></div>@endforeach</div></section>
    <aside class="dashboard-panel breakdown-panel"><div class="panel-heading"><div><h2>Payment breakdown</h2><p>By collection type</p></div></div><div class="breakdown-row"><span><i class="dot red"></i>Traffic violations</span><strong>58%</strong></div><div class="breakdown-row"><span><i class="dot orange"></i>Parking violations</span><strong>27%</strong></div><div class="breakdown-row"><span><i class="dot gray"></i>Impound fees</span><strong>15%</strong></div></aside>
  </div>
  <section class="table-card payment-table-card"><div class="panel-heading"><div><h2>Recent transactions</h2><p>Latest payment activity</p></div><button class="outline-action" type="button">Export</button></div><div class="table-scroll"><table class="users-table"><thead><tr><th>Reference</th><th>Payer</th><th>Payment type</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>@foreach ($transactions as $transaction)<tr><td><span class="payment-reference">{{ $transaction['reference'] }}</span></td><td><strong>{{ $transaction['payer'] }}</strong></td><td>{{ $transaction['type'] }}</td><td>{{ $transaction['date'] }}</td><td class="payment-amount">{{ $transaction['amount'] }}</td><td><span class="payment-status status-{{ strtolower($transaction['status']) }}">{{ $transaction['status'] }}</span></td></tr>@endforeach</tbody></table></div></section>
@endsection
