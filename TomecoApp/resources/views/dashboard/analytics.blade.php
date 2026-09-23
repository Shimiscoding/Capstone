@extends('layouts.admin-dashboard')

@section('title', 'Analytics')
@section('activePage', 'analytics')

@section('content')
    <div class="page-head home-page-head">
        <div>
            <p class="eyebrow">Performance intelligence</p>
            <h1>Analytics</h1>
            <p class="page-description">Track violation patterns, account growth, and the vehicle types most often cited.</p>
        </div>
        <div class="analytics-controls">
            <form method="GET" action="{{ route('dashboard.analytics') }}"><label
                    class="analytics-filter"><span>Period</span><select name="period" onchange="this.form.submit()">
                        <option value="daily" @selected($period === 'daily')>Daily</option>
                        <option value="monthly" @selected($period === 'monthly')>Monthly</option>
                        <option value="yearly" @selected($period === 'yearly')>Yearly</option>
                    </select></label></form>
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
    </div>

    <div class="analytics-kpi-grid">
        <article class="analytics-kpi"><span>Violations</span><strong>{{ number_format($periodViolationTotal) }}</strong><small>During the {{ $periodDescription }}</small></article>
        <article class="analytics-kpi"><span>New users</span><strong>{{ number_format($periodUserTotal) }}</strong><small>During the {{ $periodDescription }}</small></article>
    </div>

    <div class="analytics-graph-grid">
    <section class="dashboard-panel overall-line-panel analytics-chart-card">
        <div class="panel-heading">
            <div>
                <h2 id="analyticsChartTitle">Overall Activity</h2>
                <p>Activity recorded during the {{ $periodDescription }}</p>
            </div>
        </div>
        <div class="chartjs-container"><canvas id="analyticsChart" aria-label="Overall activity chart"
                role="img"></canvas></div>
    </section>

    <section class="dashboard-panel overall-line-panel analytics-chart-card">
        <div class="panel-heading">
            <div>
                <h2>Most Common Vehicle Types</h2>
                <p>Vehicle types with the most violations during the {{ $periodDescription }}</p>
            </div>
        </div>
        <div class="chartjs-container"><canvas id="vehicleTrendChart" aria-label="Vehicle types with the most violations" role="img"></canvas></div>
        @if ($vehicleTrends->isEmpty())<p class="analytics-empty-note">No vehicle data recorded for this period.</p>@endif
    </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
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
                overall: 'Overall Activity',
                violators: 'Violator Activity',
                users: 'User Activity'
            };
            const filter = document.getElementById('analyticsSeriesFilter');
            const typeFilter = document.getElementById('analyticsChartType');
            const canvas = document.getElementById('analyticsChart');
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
                                    boxWidth: 8,
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
            };
            filter.addEventListener('change', renderChart);
            typeFilter.addEventListener('change', renderChart);
            renderChart();

            const vehicleCanvas = document.getElementById('vehicleTrendChart');
            if (vehicleCanvas) {
                new Chart(vehicleCanvas, {
                    type: 'bar',
                    data: {
                        labels: @json($vehicleTrends->pluck('vehicle_type')),
                        datasets: [{
                            label: 'Violations',
                            data: @json($vehicleTrends->pluck('total')),
                            backgroundColor: ['#991b1b', '#2563eb', '#d97706', '#15803d', '#7c3aed', '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c'],
                            borderRadius: 6,
                            borderSkipped: false,
                            barThickness: 20
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef0f2' }, border: { display: false } },
                            y: { grid: { display: false }, border: { display: false } }
                        }
                    }
                });
            }
        })();
    </script>
@endpush
