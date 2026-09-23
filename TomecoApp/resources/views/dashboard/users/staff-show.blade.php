@extends('layouts.admin-dashboard')

@section('title', $sectionLabel . ' Details')
@section('activePage', 'users-' . $section)

@section('content')
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">{{ $sectionLabel }} management</p>
            <h1>{{ $staffUser->fullName }}</h1>
            <p class="page-description">{{ $sectionLabel }} profile, contact, and location details.</p>
        </div>
        <div class="users-page-actions"><a class="page-button detail-back-button"
                href="{{ $section === 'admins' ? route('dashboard.users.admins') : route('dashboard.users.enforcers') }}"
                aria-label="Back to {{ $section }}" title="Back to {{ $section }}">&larr;</a><a
                class="add-user-button" href="{{ route('dashboard.users.edit', ['user' => $staffUser, 'from' => $section]) }}">Edit
                {{ strtolower($sectionLabel) }}</a></div>
    </div>
    <br>
    <section class="create-user-card staff-detail-card">
        <div class="staff-detail-heading"><span
                class="table-avatar">{{ collect(explode(' ', $staffUser->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?:'U' }}</span>
            <div>
                <h2>{{ $staffUser->fullName }}</h2>
                <p><span class="role-badge role-{{ $staffUser->role }}">{{ $sectionLabel }}</span> <span
                        class="status-badge"><i></i> Registered</span></p>
            </div>
        </div>
        <dl class="user-detail-list staff-detail-list">
            <div>
                <dt>Username</dt>
                <dd>{{ $staffUser->username ? '@' . $staffUser->username : '—' }}</dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>{{ $staffUser->email ?: '—' }}</dd>
            </div>
            <div>
                <dt>Phone number</dt>
                <dd>{{ $staffUser->phoneNumber ?: '—' }}</dd>
            </div>
            @if ($staffUser->isOfficer())
                <div>
                    <dt>Supervisor</dt>
                    <dd>
                        @if ($staffUser->supervisor)
                            <a class="user-name-link"
                                href="{{ route('dashboard.users.supervisors.show', $staffUser->supervisor) }}">{{ $staffUser->supervisor->fullName }}</a>
                        @else
                            Unassigned
                        @endif
                    </dd>
                </div>
            @endif
            <div>
                <dt>Area</dt>
                <dd>{{ $staffUser->area ?: '—' }}</dd>
            </div>
            <div>
                <dt>Address</dt>
                <dd>{{ $staffUser->address ?: '—' }}</dd>
            </div>
            <div>
                <dt>Joined</dt>
                <dd>{{ $staffUser->created_at?->format('F j, Y') ?? '—' }}</dd>
            </div>
        </dl>
    </section>
@endsection
