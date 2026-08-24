@extends('layouts.dashboard')

@section('title', 'Analytics')
@section('activePage', 'analytics')

@section('content')
    <div class="page-head home-page-head">
        <div>
            <p class="eyebrow">System activity</p>
            <h1>Analytics</h1>
            <p class="page-description">Compare overall system activity or focus on one category.</p>
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

    <section class="dashboard-panel overall-line-panel">
        <div class="panel-heading">
            <div>
                <h2 id="analyticsChartTitle">Overall Activity</h2>
                <p>Activity recorded during the {{ $periodDescription }}</p>
            </div>
        </div>
        <div class="chartjs-container"><canvas id="analyticsChart" aria-label="Overall activity line chart"
                role="img"></canvas></div>
    </section>
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
                    borderColor: '#dc2626',
                    backgroundColor: '#dc2626'
                },
                users: {
                    label: 'Users',
                    data: @json($dailyUsers->pluck('value')),
                    borderColor: '#9333ea',
                    backgroundColor: '#9333ea'
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
                tension: .35,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: false
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
                        backgroundColor: selectedItems.map(item => item.backgroundColor),
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }];
                } else if (isDoughnut) {
                    datasets = [{
                        label: selectedItems[0].label,
                        data: selectedItems[0].data,
                        backgroundColor: ['#dbeafe', '#bfdbfe', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb',
                            '#1d4ed8'
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
        })();
    </script>
@endpush
