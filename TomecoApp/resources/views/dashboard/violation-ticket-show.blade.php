@extends('layouts.admin-dashboard')
@section('title', 'Violation ticket #'.str_pad((string) $violation->id, 7, '0', STR_PAD_LEFT))
@section('activePage', 'violation-records')
@section('workspaceClass', 'workspace-full-width')

@section('content')
@php
    $ticketNumber = '#'.str_pad((string) $violation->id, 7, '0', STR_PAD_LEFT);
    $permitType = match ($violation->license_type) { 'professional' => 'Professional', 'non_professional' => 'Non-professional', 'student_permit' => 'Student permit', default => 'Not specified' };
    $imageSource = static function (?string $value): ?string {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('/^data:image\/(?:png|jpe?g|webp);base64,[A-Za-z0-9+\/=\s]+$/i', $value)) return preg_replace('/\s+/', '', $value);
        return preg_match('/^[A-Za-z0-9+\/=\s]+$/', $value) ? 'data:image/jpeg;base64,'.preg_replace('/\s+/', '', $value) : null;
    };
    $evidenceSource = $imageSource($violation->evidence_image);
    $motoristSignatureSource = $imageSource($violation->signature);
    $enforcerSignatureSource = $imageSource($violation->enforcer_signature ?: $violation->enforcer?->signature);
@endphp

<header class="page-head violation-detail-page-head">
    <div><nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('dashboard.violation-records') }}">Violation records</a><span aria-hidden="true">/</span><span aria-current="page">{{ $ticketNumber }}</span></nav><h1>Violation ticket {{ $ticketNumber }}</h1><p class="page-description">Complete citation record and captured supporting evidence.</p></div>
    <a class="page-button violation-back-button" href="{{ route('dashboard.violation-records') }}"><span aria-hidden="true">&larr;</span><span>Back to records</span></a>
</header>

<div class="violation-detail-layout">
    <main class="violation-detail-main">
        <section class="violation-detail-card"><header class="violation-detail-section-head"><h2>Driver and permit</h2></header><dl class="violation-detail-list">
            <div><dt>Full name</dt><dd>{{ $violation->full_name ?: 'Not recorded' }}</dd></div><div><dt>D/L permit number</dt><dd>{{ $violation->license_number ?: 'Not recorded' }}</dd></div>
            <div><dt>First name</dt><dd>{{ $violation->first_name ?: '—' }}</dd></div><div><dt>Middle name</dt><dd>{{ $violation->middle_name ?: '—' }}</dd></div>
            <div><dt>Last name</dt><dd>{{ $violation->last_name ?: '—' }}</dd></div><div><dt>Permit type</dt><dd>{{ $permitType }}</dd></div>
        </dl></section>
        <section class="violation-detail-card"><header class="violation-detail-section-head"><h2>Vehicle documents</h2></header><dl class="violation-detail-list">
            <div><dt>Plate number</dt><dd>{{ $violation->plate_number ?: 'Not recorded' }}</dd></div><div><dt>Vehicle type</dt><dd>{{ $violation->vehicle_type ?: 'Not recorded' }}</dd></div>
            <div><dt>Official receipt (OR)</dt><dd>{{ $violation->or_number ?: 'Not recorded' }}</dd></div><div><dt>Certificate of registration (CR)</dt><dd>{{ $violation->cr_number ?: 'Not recorded' }}</dd></div>
        </dl></section>
        <section class="violation-detail-card"><header class="violation-detail-section-head"><h2>Evidence image</h2><span class="record-state {{ $evidenceSource ? 'is-recorded' : 'is-missing' }}">{{ $evidenceSource ? 'Recorded' : 'Not recorded' }}</span></header>@if($evidenceSource)<figure class="violation-evidence-image"><img src="{{ $evidenceSource }}" alt="Evidence for violation ticket {{ $ticketNumber }}"><figcaption>Evidence submitted with this citation</figcaption></figure>@else<div class="record-empty">No evidence image was submitted for this ticket.</div>@endif</section>
        <section class="violation-signatures-grid" aria-label="Ticket signatures">
            @foreach ([['Driver signature', $motoristSignatureSource, $violation->full_name ?: 'Driver'], ['Enforcer signature', $enforcerSignatureSource, $violation->enforcer_name ?: 'Enforcer']] as [$label, $source, $name])
                <article class="violation-detail-card signature-record"><header class="violation-detail-section-head"><h2>{{ $label }}</h2><span class="record-state {{ $source ? 'is-recorded' : 'is-missing' }}">{{ $source ? 'Recorded' : 'Not recorded' }}</span></header><div class="signature-record-body">@if($source)<img src="{{ $source }}" alt="{{ $name }} signature"><p>{{ $name }} · Captured for ticket {{ $ticketNumber }}</p>@else<div class="record-empty">No signature was captured.</div>@endif</div></article>
            @endforeach
        </section>
    </main>
    <aside class="violation-detail-card violation-citation-card"><header class="violation-detail-section-head"><h2>Citation summary</h2><span class="status-badge {{ $violation->status === 'paid' ? '' : 'status-pending' }}"><i></i>{{ ucfirst($violation->status ?: 'unpaid') }}</span></header><dl class="violation-detail-list">
        <div><dt>Violation</dt><dd>{{ $violation->violation_type ?: 'Not recorded' }}</dd></div><div class="violation-fine-row"><dt>Assessed fine</dt><dd>&#8369;{{ number_format($violation->fine_amount, 2) }}</dd></div>
        <div><dt>Issued</dt><dd>{{ $violation->created_at?->format('M d, Y · h:i A') ?? 'Not recorded' }}</dd></div><div><dt>Location</dt><dd>{{ $violation->location ?: 'Not recorded' }}</dd></div>
        <div><dt>Issuing enforcer</dt><dd>{{ $violation->enforcer_name ?: $violation->enforcer?->fullName ?: 'Not recorded' }}</dd></div>
    </dl></aside>
</div>
@endsection
