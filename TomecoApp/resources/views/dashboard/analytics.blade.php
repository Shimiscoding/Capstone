@extends('layouts.admin-dashboard')

@section('title', 'Analytics Reports')
@section('activePage', 'analytics')
@section('workspaceClass', 'analytics-reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/analytics-reports.css') }}?v={{ filemtime(public_path('css/analytics-reports.css')) }}">
@endpush

@section('content')
    <div class="page-head home-page-head">
        <div>
            <p class="eyebrow">Performance intelligence</p>
            <h1>Analytics Reports</h1>
            <p class="page-description">Monitor ticket activity, vehicle trends, and the violations recorded most often.</p>
        </div>
        <button type="button" class="analytics-report-button" id="analyticsPrint">Print report</button>
    </div>
    <div class="analytics-summary-heading">
        <p>Ticket summary <span>&middot; {{ ucfirst($summaryDescription) }}</span></p>
        <form method="GET" action="{{ route('dashboard.analytics') }}" class="analytics-summary-ranges" aria-label="Analytics time range">
            <input type="hidden" name="view" value="{{ request('view', 'overall') }}">
            <input type="hidden" name="chart" value="{{ request('chart', 'line') }}">
            @foreach (['today' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'all' => 'All'] as $value => $label)
                <button type="submit" name="summary" value="{{ $value }}" aria-pressed="{{ $summaryRange === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </form>
    </div>
    <div class="analytics-kpi-grid" aria-label="Ticket summary">
        <article class="analytics-kpi"><span>Total Tickets</span><strong>{{ number_format($summary['total']) }}</strong><small>Issued {{ $summaryDescription }}</small></article>
        <article class="analytics-kpi"><span>Active Enforcers</span><strong>{{ number_format($summary['enforcers']) }}</strong><small>Enforcers who issued tickets in this range</small></article>
        <article class="analytics-kpi"><span>Pending Tickets</span><strong>{{ number_format($summary['pending']) }}</strong><small>Unpaid tickets awaiting resolution</small></article>
        <article class="analytics-kpi"><span>Resolved Tickets</span><strong>{{ number_format($summary['resolved']) }}</strong><small>Paid tickets &middot; {{ $summary['total'] ? round($summary['resolved'] / $summary['total'] * 100, 1) : 0 }}% of this range</small></article>
    </div>
    <div class="analytics-graph-grid">
        <section class="dashboard-panel overall-line-panel analytics-chart-card">
            <div class="panel-heading"><div><h2 id="analyticsChartTitle">Performance Overview</h2><p>Activity recorded {{ $periodDescription }}</p></div></div>
        <div class="analytics-controls">
            <label class="analytics-filter"><span>View</span><select id="analyticsSeriesFilter">
                    <option value="overall">Overall</option>
                    <option value="violators">Violators</option>
                    <option value="users">Users</option>
                </select></label>
            <label class="analytics-filter"><span>Chart type</span><select id="analyticsChartType">
                    <option value="line">Line chart</option>
                    <option value="bar">Bar chart</option>
                    <option value="doughnut">Doughnut chart</option>
                </select></label>
        </div>
            <div class="chartjs-container"><canvas id="analyticsChart" aria-label="Recorded activity chart" role="img"></canvas></div>
            <p id="analyticsChartFeedback" class="analytics-chart-feedback" role="status" hidden>The activity chart could not load. Refresh the page to try again. Ticket summaries and rankings remain available.</p>
            @if ($periodViolationTotal === 0 && $periodUserTotal === 0)
                <p class="analytics-report-note">No activity recorded during this period. Choose a longer range to view earlier records.</p>
            @endif
            <noscript><p class="analytics-report-note">Enable JavaScript to view and filter the activity chart.</p></noscript>
        </section>
        <section class="dashboard-panel analytics-chart-card analytics-vehicle-panel">
            <div class="panel-heading"><div><h2>Key Performance</h2><p>Most Common Vehicle Types &middot; Vehicles most frequently issued tickets</p></div></div>
            @php
                $topVehicles = $vehicleTrends->take(3);
                $otherVehicles = $periodViolationTotal - $topVehicles->sum('total');
            @endphp
            @if ($topVehicles->isNotEmpty())
                <div class="analytics-vehicle-bubbles" aria-hidden="true">
                    @foreach ($topVehicles as $vehicle)
                        <div class="analytics-vehicle-bubble"><strong>{{ round($vehicle->total / $periodViolationTotal * 100, 1) }}%</strong><span>{{ $vehicle->vehicle_type }}</span></div>
                    @endforeach
                </div>
                <div class="analytics-vehicle-legend">
                    @foreach ($topVehicles as $vehicle)
                        <div><strong>{{ number_format($vehicle->total) }}</strong><span>{{ $vehicle->vehicle_type }} &middot; {{ round($vehicle->total / $periodViolationTotal * 100, 1) }}%</span></div>
                    @endforeach
                </div>
                <div class="analytics-vehicle-insight"><strong>{{ $topVehicles->first()->vehicle_type }} leads issued tickets</strong><p>Percentages use all {{ number_format($periodViolationTotal) }} tickets in this period. Other or unrecorded vehicle types: {{ number_format($otherVehicles) }}.</p></div>
            @else
                <p class="analytics-report-empty">No vehicle types recorded during this period.<br>Choose a longer range to view earlier records.</p>
            @endif
        </section>
    </div>
    <section class="analytics-violation-table">
        <div class="panel-heading"><div><h2>Most Recorded Violations</h2><p>Ranked by issued tickets {{ $periodDescription }}</p></div><span class="analytics-report-caption">All recorded violation types</span></div>
        <div class="analytics-report-table-scroll" tabindex="0" role="region" aria-label="Violation rankings, scroll horizontally for more columns">
            <table><thead><tr><th scope="col">Violation type</th><th scope="col">Tickets issued</th><th scope="col">Share of tickets</th><th scope="col">Pending</th><th scope="col">Resolved</th></tr></thead><tbody>
                @forelse ($violationTrends as $violation)
                    @php $share = $periodViolationTotal ? round($violation->total / $periodViolationTotal * 100, 1) : 0; @endphp
                    <tr><td>{{ $violation->violation_type ?: 'Unrecorded violation type' }}</td><td>{{ number_format($violation->total) }}</td><td><span class="analytics-share">{{ $share }}%<span class="analytics-share-track" aria-hidden="true"><i style="width: {{ $share }}%"></i></span></span></td><td>{{ number_format($violation->total - $violation->resolved) }}</td><td>{{ number_format($violation->resolved) }}</td></tr>
                @empty
                    <tr><td colspan="5"><p class="analytics-report-empty">No tickets recorded during this period. Choose a longer range to view earlier records.</p></td></tr>
                @endforelse
            </tbody></table>
        </div>
        @if ($violationTrends->isNotEmpty())
            <p class="analytics-report-note">Each ticket counts once per group; a ticket can appear in several groups, so shares can total more than 100%. Resolved means paid; pending means not yet paid.</p>
        @endif
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            document.getElementById('analyticsPrint').addEventListener('click', () => window.print());
            const params = new URLSearchParams(location.search);
            const labels = @json($dailyUsers->pluck('label'));
            const allDatasets = {
                violators: {
                    label: 'Violators',
                    data: @json($dailyViolations->pluck('value')),
                    borderColor: '#991b1b',
                    backgroundColor: 'rgba(153, 27, 27, .10)',
                    chartColor: '#991b1b'
                },
                users: {
                    label: 'Users',
                    data: @json($dailyUsers->pluck('value')),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, .10)',
                    chartColor: '#2563eb'
                }
            };
            const chartTitles = {
                overall: 'Performance Overview',
                violators: 'Performance Overview - Violators',
                users: 'Performance Overview - Users'
            };
            const filter = document.getElementById('analyticsSeriesFilter');
            const typeFilter = document.getElementById('analyticsChartType');
            const canvas = document.getElementById('analyticsChart');
            if (['overall', 'violators', 'users'].includes(params.get('view'))) filter.value = params.get('view');
            if (['line', 'bar', 'doughnut'].includes(params.get('chart'))) typeFilter.value = params.get('chart');
            let chart;
            const dataset = item => ({
                ...item,
                tension: .3,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5,
                fill: true
            });
            const renderChart = () => {
                const selected = filter.value;
                const type = typeFilter.value;
                const isDoughnut = type === 'doughnut';
                const selectedItems = selected === 'overall' ? Object.values(allDatasets) : [allDatasets[selected]];
                let chartLabels = labels;
                let datasets = selectedItems.map(dataset);

                if (isDoughnut && selected === 'overall') {
                    chartLabels = selectedItems.map(item => item.label);
                    datasets = [{
                        label: 'Total activity',
                        data: selectedItems.map(item => item.data.reduce((total, value) => total + value,
                            0)),
                        backgroundColor: selectedItems.map(item => item.chartColor),
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }];
                } else if (isDoughnut) {
                    datasets = [{
                        label: selectedItems[0].label,
                        data: selectedItems[0].data,
                        backgroundColor: ['#991b1b', '#2563eb', '#d97706', '#15803d', '#7c3aed', '#0891b2',
                            '#db2777'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }];
                }

                chart?.destroy();
                if (typeof Chart === 'undefined') {
                    document.getElementById('analyticsChartFeedback').hidden = false;
                    return;
                }
                chart = new Chart(canvas, {
                    type,
                    data: {
                        labels: chartLabels,
                        datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: isDoughnut ? 'nearest' : 'index',
                            intersect: isDoughnut
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    pointStyleWidth: 6,
                                    boxWidth: 6,
                                    boxHeight: 6,
                                    generateLabels: chart => {
                                        const generate = isDoughnut
                                            ? Chart.overrides.doughnut.plugins.legend.labels.generateLabels
                                            : Chart.defaults.plugins.legend.labels.generateLabels;
                                        return generate(chart).map(label => ({
                                            ...label,
                                            pointStyle: 'circle',
                                            fillStyle: isDoughnut ? label.fillStyle : label.strokeStyle,
                                            lineWidth: 0
                                        }));
                                    },
                                    font: {
                                        weight: '600'
                                    }
                                }
                            }
                        },
                        scales: isDoughnut ? {} : {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                grid: {
                                    color: '#e5e7eb'
                                }
                            }
                        }
                    }
                });
                document.getElementById('analyticsChartTitle').textContent = chartTitles[selected];
                canvas.setAttribute('aria-label', `${chartTitles[selected]}: ${type} chart of recorded activity`);
                params.set('view', selected); params.set('chart', type);
                document.querySelector('.analytics-summary-ranges input[name="view"]').value = selected;
                document.querySelector('.analytics-summary-ranges input[name="chart"]').value = type;
                history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
            };
            filter.addEventListener('change', renderChart);
            typeFilter.addEventListener('change', renderChart);
            renderChart();

        })();
    </script>
@endpush
