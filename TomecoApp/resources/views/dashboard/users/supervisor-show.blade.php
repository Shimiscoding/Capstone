@extends('layouts.dashboard')

@section('title', 'Supervisor Details')
@section('activePage', 'users-supervisors')

@section('content')
  <div class="page-head users-page-head">
    <div><p class="eyebrow">Supervisor management</p><h1>{{ $supervisor->fullName }}</h1><p class="page-description">View the enforcers assigned to this supervisor.</p></div>
    <div class="users-page-actions"><a class="page-button detail-back-button" href="{{ route('dashboard.users.supervisors') }}" aria-label="Back to supervisors" title="Back to supervisors">&larr;</a><button class="page-button supervisor-details-button" id="openSupervisorDetails" type="button">View details</button><a class="add-user-button" href="{{ route('dashboard.users.edit', $supervisor) }}">Edit supervisor</a></div>
  </div>

  <section class="table-card supervisor-enforcers-card supervisor-team-full">
    <div class="supervisor-list-heading"><div><p class="eyebrow">Team</p><h2>Assigned enforcers</h2></div><span class="user-count">{{ $supervisor->enforcers->count() }} {{ \Illuminate\Support\Str::plural('enforcer', $supervisor->enforcers->count()) }}</span></div>
    <div class="table-scroll"><table class="users-table">
      <thead><tr><th>Enforcer</th><th>Contact</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($supervisor->enforcers as $enforcer)
          <tr><td><div class="user-cell"><span class="table-avatar">{{ collect(explode(' ', $enforcer->fullName))->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') ?: 'E' }}</span><span><strong>{{ $enforcer->fullName }}</strong><small>{{ $enforcer->username ? '@'.$enforcer->username : $enforcer->email }}</small></span></div></td><td>{{ $enforcer->phoneNumber ?: '—' }}<br><small>{{ $enforcer->email }}</small></td><td class="location-cell" title="{{ collect([$enforcer->barangay, $enforcer->area, $enforcer->address])->filter()->join(', ') }}"><strong>{{ $enforcer->barangay ?: '—' }}</strong><small class="location-truncate">{{ collect([$enforcer->area, $enforcer->address])->filter()->join(', ') ?: 'No address' }}</small></td><td><span class="status-badge"><i></i> Registered</span></td><td><a class="page-button" href="{{ route('dashboard.users.edit', $enforcer) }}">Edit</a></td></tr>
        @empty
          <tr><td colspan="5" class="empty-state"><strong>No assigned enforcers</strong><span>Assign an enforcer to this supervisor from the Enforcers section.</span></td></tr>
        @endforelse
      </tbody>
    </table></div>
  </section>

  <dialog class="supervisor-details-modal" id="supervisorDetailsModal">
    <div class="supervisor-modal-heading"><div><p class="eyebrow">Supervisor profile</p><h2>{{ $supervisor->fullName }}</h2></div><button id="closeSupervisorDetails" type="button" aria-label="Close supervisor details">&times;</button></div>
    <dl class="user-detail-list supervisor-modal-details">
      <div><dt>Username</dt><dd>{{ $supervisor->username ? '@'.$supervisor->username : '—' }}</dd></div>
      <div><dt>Email</dt><dd>{{ $supervisor->email ?: '—' }}</dd></div>
      <div><dt>Phone number</dt><dd>{{ $supervisor->phoneNumber ?: '—' }}</dd></div>
      <div><dt>Barangay</dt><dd>{{ $supervisor->barangay ?: '—' }}</dd></div>
      <div><dt>Area</dt><dd>{{ $supervisor->area ?: '—' }}</dd></div>
      <div><dt>Address</dt><dd>{{ $supervisor->address ?: '—' }}</dd></div>
    </dl>
  </dialog>
@endsection

@push('scripts')
<script>
  const supervisorDetailsModal = document.getElementById('supervisorDetailsModal');
  document.getElementById('openSupervisorDetails').addEventListener('click', () => supervisorDetailsModal.showModal());
  document.getElementById('closeSupervisorDetails').addEventListener('click', () => supervisorDetailsModal.close());
  supervisorDetailsModal.addEventListener('click', event => { if (event.target === supervisorDetailsModal) supervisorDetailsModal.close(); });
</script>
@endpush
