@extends('layouts.dashboard')

@php $sectionTitle = match ($userSection ?? null) { 'supervisors' => 'Supervisors', 'enforcers' => 'Enforcers', 'admins' => 'Admins', default => 'Users' }; $sectionRoute = match ($userSection ?? null) { 'supervisors' => route('dashboard.users.supervisors'), 'enforcers' => route('dashboard.users.enforcers'), 'admins' => route('dashboard.users.admins'), default => route('dashboard.users') }; @endphp
@section('title', $sectionTitle)
@section('activePage', ($userSection ?? null) ? 'users-'.$userSection : 'users')
@section('headerSearch')
  <form class="search" method="GET" action="{{ $sectionRoute }}" role="search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="search" value="{{ $search }}" placeholder="Search {{ strtolower($sectionTitle) }}"><input type="hidden" name="sort" value="{{ $sort }}"></form>
@endsection

@section('content')
  <div class="page-head users-page-head"><div><p class="eyebrow">User management</p><h1>{{ $sectionTitle }}</h1><p class="page-description">Manage registered {{ strtolower($sectionTitle) }} and their assignment details.</p></div><div class="users-page-actions"><div class="user-count">{{ number_format($users->total()) }} {{ strtolower($sectionTitle) }}</div><a class="add-user-button" href="{{ match ($userSection ?? null) { 'supervisors' => route('dashboard.users.supervisors.create'), 'enforcers' => route('dashboard.users.enforcers.create'), 'admins' => route('dashboard.users.admins.create'), default => route('dashboard.users.create') } }}">+ Add {{ strtolower(\Illuminate\Support\Str::singular($sectionTitle)) }}</a></div></div>
  @if (session('success'))<div class="success-message" role="status">{{ session('success') }}</div>@endif
  @if (session('error'))<div class="success-message" role="alert">{{ session('error') }}</div>@endif

  <form id="userTableFilters" class="user-table-filters" method="GET" action="{{ $sectionRoute }}" role="search">
    <label class="user-filter-search"><span>Search by name</span><input id="userNameSearch" type="search" name="search" value="{{ $search }}" placeholder="First, middle, or last name" autocomplete="off"></label>
    @if(!isset($userSection))<label><span>Filter by role</span><select id="userRoleFilter" name="role"><option value="" @selected($role === '')>All roles</option><option value="driver" @selected($role === 'driver')>Drivers only</option><option value="officer" @selected($role === 'officer')>Enforcers only</option><option value="supervisor" @selected($role === 'supervisor')>Supervisors only</option><option value="admin" @selected($role === 'admin')>Admins only</option></select></label>@endif
    <label><span>Sort users</span><select id="userSort" name="sort"><option value="latest" @selected($sort === 'latest')>Newest first</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="name_asc" @selected($sort === 'name_asc')>Name A–Z</option><option value="name_desc" @selected($sort === 'name_desc')>Name Z–A</option></select></label>
    <button type="submit">Search</button><a href="{{ $sectionRoute }}">Clear filters</a>
  </form>

  <div class="table-card" aria-live="polite"><div class="table-scroll"><table class="users-table">
    <thead><tr><th scope="col">User</th><th scope="col">Location</th>@if(!in_array(($userSection ?? null), ['admins', 'supervisors'], true))<th scope="col">Assignment</th>@endif<th scope="col">Phone number</th><th scope="col">Role</th><th scope="col">Joined</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
    <tbody>
      @forelse ($users as $listedUser)
        @php $listedName = $listedUser->fullName ?: 'Unnamed user'; $listedInitials = collect(explode(' ', $listedName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join(''); @endphp
        <tr>
          <td><div class="user-cell"><span class="table-avatar">{{ $listedInitials ?: 'U' }}</span><span><strong>@if(in_array(($userSection ?? null), ['supervisors', 'enforcers', 'admins'], true))<a class="user-name-link" href="{{ match($userSection) { 'supervisors' => route('dashboard.users.supervisors.show', $listedUser), 'enforcers' => route('dashboard.users.enforcers.show', $listedUser), 'admins' => route('dashboard.users.admins.show', $listedUser) } }}">{{ $listedName }}</a>@else{{ $listedName }}@endif</strong><small>{{ $listedUser->username ? '@'.$listedUser->username.' · ' : '' }}{{ $listedUser->email }}</small></span></div></td>
          <td class="location-cell" title="{{ collect([$listedUser->barangay, $listedUser->area, $listedUser->address])->filter()->join(', ') }}"><strong>{{ $listedUser->barangay ?: '—' }}</strong><small class="location-truncate">{{ collect([$listedUser->area, $listedUser->address])->filter()->join(', ') ?: 'No address' }}</small></td>@if(!in_array(($userSection ?? null), ['admins', 'supervisors'], true))<td>@if($listedUser->isOfficer())<strong>Supervisor:</strong> {{ $listedUser->supervisor?->fullName ?? 'Unassigned' }}@else<span>{{ $listedUser->plateNumber ?: '—' }}</span>@endif</td>@endif<td>{{ $listedUser->phoneNumber ?: '—' }}</td><td><span class="role-badge role-{{ $listedUser->role }}">{{ $listedUser->isOfficer() ? 'Enforcer' : ucfirst($listedUser->role) }}</span></td><td>{{ $listedUser->created_at?->format('M d, Y') ?? '—' }}</td><td><span class="status-badge"><i></i> Registered</span></td>
          <td><div class="user-row-actions"><details class="user-actions-menu"><summary aria-label="Actions for {{ $listedName }}" title="Actions"><span aria-hidden="true">&#8942;</span></summary><div class="user-actions-dropdown" role="menu"><a class="user-action action-edit" role="menuitem" href="{{ route('dashboard.users.edit', $listedUser) }}">Edit</a><form method="POST" action="{{ route('dashboard.users.destroy', $listedUser) }}" onsubmit="return confirm('Delete this user? This cannot be undone.')">@csrf @method('DELETE') @if(isset($userSection))<input type="hidden" name="user_section" value="{{ $userSection }}">@endif<button class="user-action action-delete" role="menuitem" type="submit">Delete</button></form></div></details></div></td>
        </tr>
      @empty
        <tr><td colspan="{{ in_array(($userSection ?? null), ['admins', 'supervisors'], true) ? 7 : 8 }}" class="empty-state"><strong>{{ $search !== '' ? 'No users found' : 'No registered users yet' }}</strong><span>{{ $search !== '' ? 'Try a different name.' : 'Newly registered accounts will appear here.' }}</span></td></tr>
      @endforelse
    </tbody>
  </table></div>
  @if ($users->hasPages())<div class="table-footer"><span>Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span><div class="pagination-actions">@if ($users->onFirstPage())<span class="page-button is-disabled">Previous</span>@else<a class="page-button" href="{{ $users->previousPageUrl() }}">Previous</a>@endif @if ($users->hasMorePages())<a class="page-button" href="{{ $users->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled">Next</span>@endif</div></div>@endif
  </div>
@endsection

@push('scripts')
<script>
  const userFilters = document.getElementById('userTableFilters'); const userNameSearch = document.getElementById('userNameSearch'); let searchTimer; let activeRequest;
  async function updateUserTable(url, pushState = true) {
    activeRequest?.abort(); activeRequest = new AbortController(); const currentTable = document.querySelector('.table-card'); currentTable.classList.add('is-loading');
    try {
      const response = await fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'}, signal: activeRequest.signal }); if (!response.ok) throw new Error('Unable to load users');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html'); const nextTable = page.querySelector('.table-card'); const nextCount = page.querySelector('.user-count'); if (!nextTable || !nextCount) throw new Error('Invalid user response');
      nextTable.classList.add('is-entering'); currentTable.replaceWith(nextTable); document.querySelector('.user-count').replaceWith(nextCount); userNameSearch.value = page.getElementById('userNameSearch')?.value ?? ''; if (document.getElementById('userRoleFilter')) document.getElementById('userRoleFilter').value = page.getElementById('userRoleFilter')?.value ?? ''; document.getElementById('userSort').value = page.getElementById('userSort')?.value ?? 'latest'; requestAnimationFrame(() => nextTable.classList.remove('is-entering')); if (pushState) history.pushState({}, '', url);
    } catch (error) { if (error.name !== 'AbortError') window.location.assign(url); }
  }
  function applyUserFilters() { const query = new URLSearchParams(new FormData(userFilters)); updateUserTable(`${userFilters.action}?${query.toString()}`); }
  document.getElementById('userRoleFilter')?.addEventListener('change', applyUserFilters); document.getElementById('userSort').addEventListener('change', applyUserFilters); userFilters.addEventListener('submit', event => { event.preventDefault(); applyUserFilters(); }); userNameSearch.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyUserFilters, 350); });
  document.addEventListener('click', event => { const pageLink = event.target.closest('.pagination-actions a'); if (!pageLink) return; event.preventDefault(); updateUserTable(pageLink.href); }); userFilters.querySelector('a')?.addEventListener('click', event => { event.preventDefault(); updateUserTable(event.currentTarget.href); }); window.addEventListener('popstate', () => updateUserTable(window.location.href, false));
  document.addEventListener('click', event => {
    const clickedMenu = event.target.closest('.user-actions-menu');
    document.querySelectorAll('.user-actions-menu[open]').forEach(menu => { if (menu !== clickedMenu) menu.removeAttribute('open'); });
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') document.querySelectorAll('.user-actions-menu[open]').forEach(menu => menu.removeAttribute('open')); });
</script>
@endpush
