@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('activePage', 'dashboard')

@section('content')
  <div class="page-head home-page-head">
    <div><p class="eyebrow">TOMECO overview</p><h1>Home</h1><p class="page-description">Welcome, {{ auth()->user()->fullName }}. Here's what's happening today.</p></div>
    <div class="dashboard-date">{{ now()->format('F d, Y') }}</div>
  </div>

  <div class="overview-grid">
    <article class="overview-card overview-card-primary"><div class="overview-icon">U</div><div><p>Total registered users</p><strong>{{ number_format($totalUsers) }}</strong><span>All TOMECO accounts</span></div></article>
    <article class="overview-card"><div class="overview-icon">+</div><div><p>New users this month</p><strong>{{ number_format($newUsersThisMonth) }}</strong><span>{{ now()->format('F Y') }}</span></div></article>
    <article class="overview-card"><div class="overview-icon">A</div><div><p>Account status</p><strong>Active</strong><span>System access protected</span></div></article>
  </div>

  <div class="home-content-grid">
    <section class="dashboard-panel">
      <div class="panel-heading"><div><h2>Recently registered</h2><p>Latest accounts added to TOMECO</p></div><a href="{{ route('dashboard.users') }}">View all users</a></div>
      <div class="recent-list">
        @forelse ($recentUsers as $recentUser)
          <div class="recent-user"><span class="table-avatar">{{ strtoupper(substr($recentUser->fullName ?: 'U', 0, 1)) }}</span><span class="recent-details"><strong>{{ $recentUser->fullName ?: 'Unnamed user' }}</strong><small>{{ $recentUser->email }}</small></span><span class="recent-date">{{ $recentUser->created_at?->format('M d, Y') ?? '-' }}</span></div>
        @empty
          <div class="home-empty">No registered users yet.</div>
        @endforelse
      </div>
    </section>
    <aside class="dashboard-panel quick-panel">
      <div class="panel-heading"><div><h2>Quick actions</h2><p>Common dashboard tasks</p></div></div>
      <a class="quick-action" href="{{ route('dashboard.users') }}"><span>View users</span><b>&rarr;</b></a>
      <div class="quick-action is-disabled"><span>Review payments</span><small>Coming soon</small></div>
      <div class="quick-action is-disabled"><span>View analytics</span><small>Coming soon</small></div>
    </aside>
  </div>
@endsection
