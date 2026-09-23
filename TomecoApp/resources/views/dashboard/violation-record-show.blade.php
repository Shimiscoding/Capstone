@extends('layouts.admin-dashboard')

@section('title', $violation->full_name.' Violation History')
@section('activePage', 'violation-records')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    @php
        $motoristName = $violation->full_name ?: 'Unknown motorist';
        $permitType = match ($violation->license_type) {
            'professional' => 'Professional',
            'non_professional' => 'Non-professional',
            'student_permit' => 'Student permit',
            'other' => 'Other',
            default => 'Not specified',
        };
        $signedViolation = $motoristViolations->first(fn ($record) => filled($record->signature));
        $imageSource = static function (?string $value): ?string {
            $value = trim((string) $value);

            if (preg_match('/^data:image\/(?:png|jpe?g|webp);base64,[A-Za-z0-9+\/=\s]+$/i', $value)) {
                return $value;
            }

            return $value !== '' && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $value)
                ? 'data:image/png;base64,'.preg_replace('/\s+/', '', $value)
                : null;
        };
        $signatureSource = $imageSource($signedViolation?->signature);
    @endphp

    <div class="page-head users-page-head violation-detail-page-head">
        <div class="users-heading-copy">
            <p class="eyebrow">Violation records</p>
            <h1>Motorist details</h1>
            <p class="page-description">Review the driver profile and complete citation history.</p>
        </div>
        <a class="page-button detail-back-button violation-back-button" href="{{ route('dashboard.violation-records') }}">
            <span aria-hidden="true">&larr;</span><span>Back to records</span>
        </a>
    </div>

    <section class="create-user-card staff-detail-card violation-motorist-details">
        <div class="staff-detail-heading">
            <span class="table-avatar">{{ collect(explode(' ', $motoristName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?: 'M' }}</span>
            <div>
                <h2>{{ $motoristName }}</h2>
                <p><span class="role-badge">Motorist</span><span class="status-badge"><i></i>{{ $motoristViolations->count() }} {{ \Illuminate\Support\Str::plural('citation', $motoristViolations->count()) }}</span></p>
            </div>
        </div>
        <dl class="user-detail-list staff-detail-list">
            <div><dt>First name</dt><dd>{{ $violation->first_name ?: '—' }}</dd></div>
            <div><dt>Middle name</dt><dd>{{ $violation->middle_name ?: '—' }}</dd></div>
            <div><dt>Last name</dt><dd>{{ $violation->last_name ?: '—' }}</dd></div>
            <div><dt>D/L permit number</dt><dd>{{ $violation->license_number ?: '—' }}</dd></div>
            <div><dt>Permit type</dt><dd>{{ $permitType }}</dd></div>
            <div><dt>Vehicle type</dt><dd>{{ $violation->vehicle_type ?: 'Not specified' }}</dd></div>
            <div><dt>Total assessed fines</dt><dd>&#8369;{{ number_format($motoristViolations->sum('fine_amount'), 2) }}</dd></div>
            <div class="motorist-signature-detail">
                <dt>Motorist signature</dt>
                <dd>
                    @if ($signatureSource)
                        <img src="{{ $signatureSource }}" alt="{{ $motoristName }} signature">
                        <small>Captured for ticket #{{ str_pad((string) $signedViolation->id, 7, '0', STR_PAD_LEFT) }}</small>
                    @else
                        <span class="signature-empty">Not recorded</span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="table-card">
        <div class="table-scroll">
            <table class="users-table">
                <thead><tr><th>Ticket</th><th>Violation</th><th>Plate number</th><th>OR / CR</th><th>Location</th><th>Enforcer</th><th>Date issued</th><th>Fine</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($motoristViolations as $record)
                        @php($enforcerSignature = $imageSource($record->enforcer?->signature))
                        <tr>
                            <td><div class="user-cell"><span class="table-avatar">T</span><span><strong>#{{ str_pad((string) $record->id, 7, '0', STR_PAD_LEFT) }}</strong><small>{{ $record->created_at?->format('h:i A') }}</small></span></div></td>
                            <td><strong>{{ $record->violation_type ?: '—' }}</strong></td>
                            <td><span class="badge-number">{{ $record->plate_number ?: '—' }}</span></td>
                            <td><span class="violation-permit-cell"><span>OR: {{ $record->or_number ?: '—' }}</span><small>CR: {{ $record->cr_number ?: '—' }}</small></span></td>
                            <td>{{ $record->location ?: '—' }}</td>
                            <td>
                                <div class="citation-enforcer">
                                    @if ($record->enforcer)
                                        <strong>{{ $record->enforcer_name ?: $record->enforcer->fullName }}</strong>
                                        <small>Issuing enforcer</small>
                                        @if ($enforcerSignature)
                                            <img src="{{ $enforcerSignature }}" alt="{{ $record->enforcer_name ?: $record->enforcer->fullName }} signature">
                                        @else
                                            <span>Signature not recorded</span>
                                        @endif
                                    @elseif ($record->enforcer_name)
                                        <strong>{{ $record->enforcer_name }}</strong>
                                        <small>Issuing enforcer (archived account)</small>
                                    @else
                                        <strong>Not recorded</strong>
                                        <small>Legacy ticket</small>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $record->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td><strong>&#8369;{{ number_format($record->fine_amount, 2) }}</strong></td>
                            <td><span class="status-badge {{ $record->status === 'paid' ? '' : 'status-pending' }}"><i></i>{{ ucfirst($record->status ?: 'unpaid') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>Showing {{ $motoristViolations->count() }} of {{ $motoristViolations->count() }} records</span></div>
    </section>
@endsection
