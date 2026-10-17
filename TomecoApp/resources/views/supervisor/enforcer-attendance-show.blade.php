@extends(auth()->user()->isAdmin() ? 'layouts.admin-dashboard' : 'layouts.supervisor-dashboard')

@section('title', $enforcer->fullName . ' Attendance')
@section('activePage', auth()->user()->isAdmin() ? 'attendance' : 'enforcer-attendance')

@section('workspaceClass', 'workspace-full-width')

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">Attendance history</p>
            <h1>{{ $enforcer->fullName }}</h1>
            <p class="page-description">Complete Time In and Time Out timestamp history for this enforcer.</p>
        </div>
        <div class="users-page-actions"><a class="page-button detail-back-button"
                href="{{ auth()->user()->isAdmin() ? route('dashboard.attendance', ['type' => 'enforcer']) : route('supervisor.enforcers.attendance.index') }}"
                aria-label="Back to attendance" title="Back to attendance">&larr;</a><span
                class="user-count">{{ number_format($attendances->total()) }} records</span></div>
    </div>

    @if (session('attendance_success'))
        <div class="success-message" role="status">{{ session('attendance_success') }}</div>
    @endif

    <x-attendance-history-table :staff="$enforcer" :attendances="$attendances" :enforcer="true" />
@endsection
