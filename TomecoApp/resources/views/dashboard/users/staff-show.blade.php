@extends('layouts.admin-dashboard')

@section('title', $sectionLabel . ' Details')
@section('activePage', 'users-' . $section)
@section('workspaceClass', 'user-editor-workspace staff-profile-workspace')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/user-editor.css') }}?v={{ filemtime(public_path('css/user-editor.css')) }}">
@endpush

@section('content')
    @php
        $sectionRoute = match ($section) {
            'supervisors' => route('dashboard.users.supervisors'),
            'admins' => route('dashboard.users.admins'),
            default => route('dashboard.users.enforcers'),
        };
    @endphp
    <div class="page-head users-page-head">
        <div>
            <p class="eyebrow">{{ $sectionLabel }} management</p>
            <h1>{{ $staffUser->fullName }}</h1>
            <p class="page-description">{{ $sectionLabel }} profile, contact, and location details.</p>
        </div>
        <div class="users-page-actions"><a class="page-button detail-back-button"
                href="{{ $sectionRoute }}"
                aria-label="Back to {{ $section }}" title="Back to {{ $section }}">&larr;</a><a
                class="add-user-button" href="{{ route('dashboard.users.edit', ['user' => $staffUser, 'from' => $section]) }}">Edit
                {{ strtolower($sectionLabel) }}</a></div>
    </div>
    <section class="create-user-card staff-profile-card" aria-label="{{ $sectionLabel }} profile">
        <div class="user-editor-photo-header">
            <div class="staff-profile-identity">
                <x-user-avatar :user="$staffUser" class="staff-profile-avatar" />
                <div class="staff-profile-badges">
                    <span class="role-badge role-{{ $staffUser->role }}">{{ $sectionLabel }}</span>
                    <span class="status-badge"><i></i> Registered</span>
                </div>
            </div>
            <div class="user-editor-photo-copy">
                <span class="user-editor-role">{{ $sectionLabel }} account</span>
                <h2>{{ $staffUser->fullName }}</h2>
                <p>Profile, contact, and location details.</p>
            </div>
        </div>
        <div class="staff-profile-body">
            <section class="staff-profile-section" aria-labelledby="staffPersonalHeading">
                <div class="form-section-heading">
                    <div><h2 id="staffPersonalHeading">Personal information</h2><p>Name and address recorded for this account.</p></div>
                </div>
                <dl class="staff-profile-details">
                    <div><dt>First name</dt><dd>{{ $staffUser->firstName ?: 'Not recorded' }}</dd></div>
                    <div><dt>Middle name</dt><dd>{{ $staffUser->middleName ?: 'Not recorded' }}</dd></div>
                    <div><dt>Last name</dt><dd>{{ $staffUser->lastName ?: 'Not recorded' }}</dd></div>
                    <div><dt>Name extension</dt><dd>{{ $staffUser->nameExtension ?: 'None' }}</dd></div>
                    <div class="staff-profile-wide"><dt>Address</dt><dd>{{ $staffUser->address ?: 'Not recorded' }}</dd></div>
                </dl>
            </section>
            <section class="staff-profile-section" aria-labelledby="staffContactHeading">
                <div class="form-section-heading">
                    <div><h2 id="staffContactHeading">Contact information</h2><p>Phone number and email address.</p></div>
                </div>
                <dl class="staff-profile-details">
                    <div><dt>Phone number</dt><dd>{{ $staffUser->phoneNumber ?: 'Not recorded' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $staffUser->email ?: 'Not recorded' }}</dd></div>
                </dl>
            </section>
            <section class="staff-profile-section" aria-labelledby="staffAccountHeading">
                <div class="form-section-heading">
                    <div><h2 id="staffAccountHeading">Account details</h2><p>{{ $staffUser->isOfficer() ? 'Account registration and current assignment.' : 'Account registration details.' }}</p></div>
                </div>
                <dl class="staff-profile-details">
                    <div><dt>Username</dt><dd>{{ $staffUser->username ? '@' . $staffUser->username : 'Not recorded' }}</dd></div>
                    <div><dt>Joined</dt><dd>{{ $staffUser->created_at?->format('F j, Y') ?? 'Not recorded' }}</dd></div>
                    @if ($staffUser->isOfficer())
                        <div><dt>Supervisor</dt><dd>
                            @if ($staffUser->supervisor)
                                <a class="user-name-link" href="{{ route('dashboard.users.supervisors.show', $staffUser->supervisor) }}">{{ $staffUser->supervisor->fullName }}</a>
                            @else
                                Unassigned
                            @endif
                        </dd></div>
                        <div><dt>Area</dt><dd>{{ $staffUser->area ?: 'Unassigned' }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </section>
@endsection
