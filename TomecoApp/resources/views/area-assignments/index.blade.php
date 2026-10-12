@extends(auth()->user()->isSupervisor() ? 'layouts.supervisor-dashboard' : 'layouts.admin-dashboard')

@section('title', 'Area Assignments')
@section('activePage', 'area-assignments')
@section('workspaceClass', 'workspace-full-width area-assignment-workspace')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/area-assignments.css') }}?v={{ filemtime(public_path('css/area-assignments.css')) }}">
@endpush

@section('content')
    <div class="page-head users-page-head">
        <div class="users-heading-copy">
            <p class="eyebrow">Field operations</p>
            <h1>{{ $canManage ? 'Enforcer Area Assignments' : 'My Assigned Area' }} <span class="users-heading-count">{{ $officers->total() }}</span></h1>
            <p class="page-description">{{ $canManage ? 'Assign operational areas to '.(auth()->user()->isAdmin() ? 'all enforcers.' : 'enforcers in your team.') : 'View the operational area assigned to you by your supervisor or administrator.' }}</p>
        </div>
    </div>

    @if (session('area_assignment_success'))
        <div class="success-message" role="status">{{ session('area_assignment_success') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash-alert is-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($canManage)
    <form class="users-reference-toolbar area-assignment-toolbar" method="GET" action="{{ route('area-assignments.index') }}">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="users-toolbar-main">
            <div class="users-toolbar-actions">
                <label class="toolbar-search">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5"/><path d="m12.2 12.2 4 4"/></svg>
                    <span class="sr-only">Search enforcers</span>
                    <input name="search" type="search" id="areaOfficerSearch" value="{{ $search }}" placeholder="Search enforcer or username">
                    <button class="area-search-clear" type="button" aria-label="Clear enforcer search" @if ($search === '') hidden @endif>&times;</button>
                </label>
                <label class="users-filter-chip">
                    <span>Area</span>
                    <select name="area">
                        <option value="">All areas</option>
                        <option value="__unassigned" @selected($area === '__unassigned')>Unassigned</option>
                        @foreach ($areas as $availableArea)
                            <option value="{{ $availableArea }}" @selected($area === $availableArea)>{{ $availableArea }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="toolbar-outline-button" type="submit">Filter</button>
                <button class="toolbar-outline-button toolbar-add-user" id="openAddArea" type="button">Add area</button>
                @if ($search !== '' || $area !== '')
                    <a class="toolbar-text-button" href="{{ route('area-assignments.index', ['type' => $type]) }}">Clear</a>
                @endif
            </div>
        </div>
    </form>
    @endif

    <section class="table-card area-assignment-card {{ $canManage ? '' : 'area-assignment-readonly' }}">
        <div class="table-scroll">
            <table class="users-table area-assignment-table">
                <thead><tr><th>Enforcer</th>@if(auth()->user()->isOfficer())<th>Supervisor / Team</th>@endif<th>Current area</th><th>{{ $canManage ? 'Assign area' : 'Assignment Status' }}</th></tr></thead>
                <tbody>
                    @forelse ($officers as $officer)
                        <tr>
                            <td><div class="user-cell"><x-user-avatar :user="$officer" /><span><strong>{{ $officer->fullName }}</strong><small>{{ $officer->username ? '@'.$officer->username : $officer->email }}</small></span></div></td>
                            @if(auth()->user()->isOfficer())
                                <td><strong>{{ $officer->supervisor?->fullName ?? 'Unassigned' }}</strong><small class="area-table-muted">{{ $officer->supervisor ? 'Supervisor-led team' : 'No team assigned' }}</small></td>
                            @endif
                            <td><span class="area-current {{ $officer->area ? '' : 'is-unassigned' }}">{{ $officer->area ?: 'Unassigned' }}</span></td>
                            <td>
                                @if ($canManage)
                                <form class="area-assignment-form" method="POST" action="{{ route('area-assignments.update', $officer) }}">
                                    @csrf @method('PUT')
                                    <label class="sr-only" for="area-{{ $officer->id }}">Area for {{ $officer->fullName }}</label>
                                    <select id="area-{{ $officer->id }}" name="area">
                                        <option value="">Unassigned</option>
                                        @foreach ($areas as $availableArea)
                                            <option value="{{ $availableArea }}" @selected(old('area', $officer->area) === $availableArea)>{{ $availableArea }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" aria-label="Save area for {{ $officer->fullName }}">Save</button>
                                </form>
                                @else
                                    <span class="area-table-muted">Contact your supervisor if this assignment needs to be changed.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->isOfficer() ? 4 : 3 }}" class="empty-state"><strong>No enforcers found</strong><span>No enforcers match the selected filters.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>Showing {{ $officers->firstItem() ?? 0 }}–{{ $officers->lastItem() ?? 0 }} of {{ $officers->total() }}</span>{{ $officers->links() }}</div>
    </section>

    @if ($canManage)
        <dialog class="attendance-restriction-modal area-add-modal" id="addAreaModal" aria-labelledby="addAreaTitle">
            <div class="attendance-modal-head">
                <div><h2 id="addAreaTitle">Add operational area</h2><p>Create an area that can be selected when assigning enforcers.</p></div>
                <button type="button" data-close-area-modal aria-label="Close modal">&times;</button>
            </div>
            <form class="attendance-restriction-form" method="POST" action="{{ route('area-assignments.areas.store') }}">
                @csrf
                <div class="form-field"><label for="newAreaName">Area or location <b>Required</b></label><input id="newAreaName" name="name" type="text" maxlength="255" required placeholder="Enter area name"></div>
                <div class="attendance-modal-actions"><button class="page-button" type="button" data-close-area-modal>Cancel</button><button type="submit">Add area</button></div>
            </form>
        </dialog>
    @endif
@endsection

@push('scripts')
@if ($canManage)
<script>
(() => {
    const search = document.getElementById('areaOfficerSearch');
    const clearSearch = document.querySelector('.area-search-clear');
    search.addEventListener('input', () => { clearSearch.hidden = !search.value; });
    clearSearch.addEventListener('click', () => { search.value = ''; clearSearch.hidden = true; search.form.requestSubmit(); });
    const modal = document.getElementById('addAreaModal');
    document.getElementById('openAddArea')?.addEventListener('click', () => modal.showModal());
    modal.querySelectorAll('[data-close-area-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
})();
</script>
@endif
@endpush
