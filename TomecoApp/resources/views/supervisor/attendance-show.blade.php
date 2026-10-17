@extends('layouts.admin-dashboard')

@section('title', $supervisor->fullName . ' Attendance')
@section('activePage', 'attendance')

@section('workspaceClass', 'workspace-full-width')

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">Attendance history</p>
            <h1>{{ $supervisor->fullName }}</h1>
            <p class="page-description">Complete Time In and Time Out timestamp history for this supervisor.</p>
        </div>
        <div class="users-page-actions"><a class="page-button detail-back-button" href="{{ route('dashboard.attendance') }}"
                aria-label="Back to attendance" title="Back to attendance">&larr;</a><span
                class="user-count">{{ number_format($attendances->total()) }} records</span></div>
    </div>

    @if (session('attendance_success'))
        <div class="success-message" role="status">{{ session('attendance_success') }}</div>
    @endif

    <x-attendance-history-table :staff="$supervisor" :attendances="$attendances" />
@endsection
