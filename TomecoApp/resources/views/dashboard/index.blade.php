@extends('layouts.admin-dashboard')

@section('title', 'Dashboard')
@section('activePage', 'dashboard')

@section('content')
    <div class="page-head home-page-head">
        <div>
            <p class="eyebrow">TOMECO operations overview</p>
            <h1>Home</h1>
            <p class="page-description">Welcome, {{ auth()->user()->fullName }}. Review issued tickets and account activity.</p>
        </div>
        <div class="dashboard-date">{{ now()->format('F d, Y') }}</div>
    </div>

    <div class="overview-grid">
        <a class="overview-card overview-card-primary" href="{{ route('dashboard.violation-records') }}">
            <div class="overview-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a3 3 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a3 3 0 0 0 0-6V6Z" />
                    <path d="M13 8h3M13 12h3M13 16h3M9 4v16" />
                </svg></div>
            <div>
                <p>Tickets issued</p>
                <strong>{{ number_format($totalTickets) }}</strong><span>{{ number_format($outstandingTickets) }} unpaid or pending</span>
            </div>
        </a>
        <a class="overview-card" href="{{ auth()->user()->isAdmin() ? route('dashboard.users.supervisors') : route('profile.edit') }}">
            <div class="overview-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 21a8 8 0 0 1 16 0" />
                </svg></div>
            <div>
                <p>Registered users</p><strong>{{ number_format($totalUsers) }}</strong><span>Supervisor, officer, and admin accounts</span>
            </div>
        </a>
    </div>

    <div class="analytics-graph-grid home-analytics-grid">
    <section class="dashboard-panel home-analytics-panel analytics-chart-card">
        <div class="panel-heading">
            <div>
                <h2>Activity overview</h2>
                <p>Violations and user registrations during the last 7 days</p>
            </div><a href="{{ route('dashboard.analytics') }}">Open analytics</a>
        </div>
        <div class="chartjs-container home-chart-container"><canvas id="homeAnalyticsChart"
                aria-label="Seven-day activity overview" role="img"></canvas></div>
    </section>
    </div>

    <div class="home-content-grid">
        <section class="dashboard-panel">
            <div class="panel-heading">
                <div>
                    <h2>Recently issued tickets</h2>
                    <p>Latest traffic violations recorded in TOMECO</p>
                </div><a href="{{ route('dashboard.violation-records') }}">Review tickets</a>
            </div>
            <div class="recent-list">
                @forelse ($recentViolations as $violation)
                    <div class="recent-user"><span class="table-avatar">T</span><span
                            class="recent-details"><strong>{{ $violation->violation_type }}</strong><small>{{ $violation->full_name }}
                                &middot; {{ $violation->plate_number }} &middot;
                                &#8369;{{ number_format($violation->fine_amount, 2) }}</small></span><span
                            class="recent-date">{{ ucfirst($violation->status) }}<br>{{ $violation->created_at?->format('M d, Y') }}</span>
                    </div>
                @empty
                    <div class="home-empty">No tickets have been issued yet.</div>
                @endforelse
            </div>
        </section>
        <aside class="dashboard-panel quick-panel">
            <div class="panel-heading">
                <div>
                    <h2>Quick actions</h2>
                    <p>Open frequently used records</p>
                </div>
            </div>
            <a class="quick-action" href="{{ route('dashboard.violation-records') }}"><span>Review tickets</span><b>&rarr;</b></a>
            @if (auth()->user()->isAdmin())
                <a class="quick-action" href="{{ route('dashboard.users.supervisors') }}"><span>Manage users</span><b>&rarr;</b></a>
            @endif
            <a class="quick-action" href="{{ route('profile.edit') }}"><span>Update my profile</span><b>&rarr;</b></a>
        </aside>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            const canvas = document.getElementById('homeAnalyticsChart');
            if (!canvas || typeof Chart === 'undefined') return;

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: @json($activityDays->pluck('label')),
                    datasets: [
                        {
                            label: 'Violations',
                            data: @json($activityDays->pluck('violations')),
                            borderColor: '#a92722',
                            backgroundColor: 'rgba(169, 39, 34, .08)',
                            tension: .3,
                            borderWidth: 2,
                            fill: true
                        },
                        {
                            label: 'Users',
                            data: @json($activityDays->pluck('users')),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, .08)',
                            tension: .3,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 7,
                                boxHeight: 7,
                                padding: 18,
                                color: '#4f554d',
                                font: { size: 12, weight: '600' }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });

        })();
    </script>
@endpush
