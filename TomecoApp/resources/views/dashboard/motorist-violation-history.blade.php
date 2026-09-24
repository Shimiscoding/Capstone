@extends('layouts.admin-dashboard')
@section('title', ($motorist->full_name ?: 'Driver').' violation history')
@section('activePage', 'violation-records')
@section('workspaceClass', 'workspace-full-width')

@section('content')
@php
    $motoristName = $motorist->full_name ?: 'Unknown driver';
    $initials = collect(preg_split('/\s+/', $motoristName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
    $permitType = match ($motorist->license_type) { 'professional' => 'Professional', 'non_professional' => 'Non-professional', 'student_permit' => 'Student permit', default => 'Not specified' };
@endphp

<header class="page-head violation-detail-page-head motorist-history-head">
    <div>
        <nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('dashboard.violation-records') }}">Violation records</a><span aria-hidden="true">/</span><span aria-current="page">{{ $motoristName }}</span></nav>
        <h1>Driver violation history</h1>
        <p class="page-description">Identity details and every ticket recorded for this driver.</p>
    </div>
    <a class="page-button violation-back-button" href="{{ route('dashboard.violation-records') }}"><span aria-hidden="true">&larr;</span><span>Back to records</span></a>
</header>

<section class="motorist-profile-card" aria-labelledby="motorist-name">
    <div class="motorist-profile-identity"><span class="motorist-profile-avatar" aria-hidden="true">{{ $initials ?: 'D' }}</span><div><p class="eyebrow">Driver record</p><h2 id="motorist-name">{{ $motoristName }}</h2><div class="motorist-profile-meta"><span>{{ $permitType }}</span><span>{{ $motoristViolations->total() }} {{ \Illuminate\Support\Str::plural('violation', $motoristViolations->total()) }}</span></div></div></div>
    <dl class="motorist-profile-facts">
        <div><dt>First name</dt><dd>{{ $motorist->first_name ?: 'Not recorded' }}</dd></div>
        <div><dt>Middle name</dt><dd>{{ $motorist->middle_name ?: 'Not recorded' }}</dd></div>
        <div><dt>Last name</dt><dd>{{ $motorist->last_name ?: 'Not recorded' }}</dd></div>
        <div><dt>D/L permit number</dt><dd>{{ $motorist->license_number ?: 'Not recorded' }}</dd></div>
        <div><dt>Permit type</dt><dd>{{ $permitType }}</dd></div>
        <div><dt>Total assessed fines</dt><dd class="motorist-total-fine">&#8369;{{ number_format($totalAssessedFines, 2) }}</dd></div>
    </dl>
</section>

<section class="motorist-history-section" aria-labelledby="history-title">
    <header class="motorist-history-section-head"><div><p class="eyebrow">Citation ledger</p><h2 id="history-title">Recorded violations</h2></div><span>{{ $motoristViolations->total() }} total</span></header>
    <div class="table-card motorist-history-table-card">
        <div class="table-scroll"><table class="users-table motorist-history-table">
            <thead><tr><th>Ticket</th><th>Violation</th><th>Vehicle</th><th>OR / CR</th><th>Location</th><th>Enforcer</th><th>Date issued</th><th>Fine / status</th><th class="actions-column">Actions</th></tr></thead>
            <tbody>
                @foreach ($motoristViolations as $record)
                    <tr>
                        <td><a class="ticket-link" href="{{ route('dashboard.violation-records.show', $record) }}">#{{ str_pad((string) $record->id, 7, '0', STR_PAD_LEFT) }}</a><small>{{ $record->created_at?->format('h:i A') }}</small></td>
                        <td><strong>{{ $record->violation_type ?: 'Not recorded' }}</strong></td>
                        <td><strong class="badge-number">{{ $record->plate_number ?: '—' }}</strong><small>{{ $record->vehicle_type ?: 'Not specified' }}</small></td>
                        <td><span>OR: {{ $record->or_number ?: '—' }}</span><small>CR: {{ $record->cr_number ?: '—' }}</small></td>
                        <td><span class="cell-wrap">{{ $record->location ?: 'Not recorded' }}</span></td>
                        <td><strong>{{ $record->enforcer_name ?: $record->enforcer?->fullName ?: 'Not recorded' }}</strong><small>{{ filled($record->enforcer_signature) ? 'Signature recorded' : 'Signature missing' }}</small></td>
                        <td>{{ $record->created_at?->format('M d, Y') ?? '—' }}</td>
                        <td><strong>&#8369;{{ number_format($record->fine_amount, 2) }}</strong><span class="status-badge {{ $record->status === 'paid' ? '' : 'status-pending' }}"><i></i>{{ ucfirst($record->status ?: 'unpaid') }}</span></td>
                        <td class="actions-column"><a class="row-view-action" href="{{ route('dashboard.violation-records.show', $record) }}" aria-label="View ticket #{{ str_pad((string) $record->id, 7, '0', STR_PAD_LEFT) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <footer class="table-footer"><span>Showing {{ $motoristViolations->firstItem() }}–{{ $motoristViolations->lastItem() }} of {{ $motoristViolations->total() }}</span>@if($motoristViolations->hasPages())<nav class="pagination-actions" aria-label="Driver violation pages">@if($motoristViolations->onFirstPage())<span class="page-button is-disabled">Previous</span>@else<a class="page-button" href="{{ $motoristViolations->previousPageUrl() }}">Previous</a>@endif @if($motoristViolations->hasMorePages())<a class="page-button" href="{{ $motoristViolations->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled">Next</span>@endif</nav>@endif</footer>
    </div>
</section>
@endsection
