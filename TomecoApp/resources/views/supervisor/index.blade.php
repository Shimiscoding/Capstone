@extends('layouts.dashboard')

@section('title', 'Supervisor Dashboard')
@section('activePage', 'dashboard')

@section('content')
    <div class="page-head home-page-head">
        <div>
            <p class="eyebrow">Supervisor workspace</p>
            <h1>Welcome, {{ $supervisor->firstName }}</h1>
            <p class="page-description">Review your assigned enforcement team and their coverage information.</p>
        </div>
        <div class="dashboard-date">{{ now()->format('F d, Y') }}</div>
    </div>

    <div class="overview-grid supervisor-overview-grid">
        <article class="overview-card overview-card-primary">
            <div class="overview-icon">E</div>
            <div>
                <p>Assigned enforcers</p><strong>{{ number_format($enforcerCount) }}</strong><span>Members currently under
                    your supervision</span>
            </div>
        </article>
        <article class="overview-card">
            <div class="overview-icon">B</div>
            <div>
                <p>Barangays covered</p><strong>{{ number_format($barangayCount) }}</strong><span>Unique barangays
                    represented by your team</span>
            </div>
        </article>
        <article class="overview-card">
            <div class="overview-icon">A</div>
            <div>
                <p>Areas covered</p><strong>{{ number_format($areaCount) }}</strong><span>Unique operational areas
                    represented</span>
            </div>
        </article>
    </div>

    <section class="table-card supervisor-team-panel">
        <div class="supervisor-list-heading">
            <div>
                <p class="eyebrow">My team</p>
                <h2>Assigned enforcers</h2>
            </div><span class="user-count">{{ $enforcerCount }}
                {{ \Illuminate\Support\Str::plural('enforcer', $enforcerCount) }}</span>
        </div>
        <div class="table-scroll">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Enforcer</th>
                        <th>Contact</th>
                        <th>Barangay</th>
                        <th>Area and address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($supervisor->enforcers as $enforcer)
                        <tr>
                            <td>
                                <div class="user-cell"><span
                                        class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?:'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@' . $enforcer->username : 'No username' }}</small></span>
                                </div>
                            </td>
                            <td>{{ $enforcer->phoneNumber ?: '—' }}<br><small>{{ $enforcer->email }}</small></td>
                            <td>{{ $enforcer->barangay ?: '—' }}</td>
                            <td class="location-cell"
                                title="{{ collect([$enforcer->area, $enforcer->address])->filter()->join(', ') }}">
                                <strong>{{ $enforcer->area ?: '—' }}</strong><small
                                    class="location-truncate">{{ $enforcer->address ?: 'No address' }}</small></td>
                            <td><span class="status-badge"><i></i> Registered</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state"><strong>No enforcers assigned yet</strong><span>Add an
                                    available enforcer from My Team.</span></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
