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
            <h1>{{ $canManage ? 'Enforcer Area Assignments' : 'My Assigned Area' }} <span class="users-heading-count">{{ number_format($officers->total()) }}</span></h1>
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
        <form class="users-reference-toolbar area-assignment-toolbar" method="GET" action="{{ route('area-assignments.index') }}" role="search" novalidate>
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
                            <option value="" @selected($area === '')>All areas</option>
                            <option value="__unassigned" @selected($area === '__unassigned')>Unassigned</option>
                            @if ($area !== '' && $area !== '__unassigned' && !$areas->contains($area))
                                <option value="{{ $area }}" selected>{{ $area }}</option>
                            @endif
                            @foreach ($areas as $availableArea)
                                <option value="{{ $availableArea }}" @selected($area === $availableArea)>{{ $availableArea }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="toolbar-outline-button" type="submit">Filter</button>
                    @if ($search !== '' || $area !== '')
                        <a class="toolbar-text-button" href="{{ route('area-assignments.index', ['type' => $type]) }}">Clear filters</a>
                    @endif
                    <button class="toolbar-outline-button toolbar-add-user" id="openAddArea" type="button"><span aria-hidden="true">+</span> Add area</button>
                </div>
            </div>
        </form>
    @endif

    <section class="table-card area-assignment-card {{ $canManage ? '' : 'area-assignment-readonly' }}" aria-label="Enforcer area assignments">
        <p class="area-table-hint" id="areaTableHint">Scroll horizontally to view assignments and save changes.</p>
        <div class="table-scroll" role="region" aria-label="Enforcer area assignments table" tabindex="0">
            <table class="users-table area-assignment-table">
                <caption class="sr-only">Enforcers and their current operational area assignments</caption>
                <thead><tr><th scope="col">Enforcer</th>@if(auth()->user()->isOfficer())<th scope="col">Supervisor / Team</th>@endif<th scope="col">Current area</th><th scope="col">{{ $canManage ? 'Assign area' : 'Assignment status' }}</th></tr></thead>
                <tbody>
                    @forelse ($officers as $officer)
                        @php
                            $isSubmittedRow = (string) old('assignment_user_id') === (string) $officer->id;
                            $selectedArea = $isSubmittedRow ? old('area', '') : ($officer->area ?? '');
                            $hasAreaError = $isSubmittedRow && $errors->has('area');
                        @endphp
                        <tr>
                            <td><div class="user-cell"><x-user-avatar :user="$officer" /><span><strong>{{ $officer->fullName }}</strong><small>{{ $officer->username ? '@'.$officer->username : $officer->email }}</small></span></div></td>
                            @if(auth()->user()->isOfficer())
                                <td><strong>{{ $officer->supervisor?->fullName ?? 'Unassigned' }}</strong><small class="area-table-muted">{{ $officer->supervisor ? 'Supervisor-led team' : 'No team assigned' }}</small></td>
                            @endif
                            <td><span class="area-current {{ $officer->area ? '' : 'is-unassigned' }}">{{ $officer->area ?: 'Unassigned' }}</span></td>
                            <td>
                                @if ($canManage)
                                    <form class="area-assignment-form" method="POST" action="{{ route('area-assignments.update', $officer) }}" data-area-save novalidate>
                                        @csrf @method('PUT')
                                        <input type="hidden" name="assignment_user_id" value="{{ $officer->id }}">
                                        <div class="area-assignment-field">
                                            <label class="sr-only" for="area-{{ $officer->id }}">Area for {{ $officer->fullName }}</label>
                                            <select id="area-{{ $officer->id }}" name="area" @if ($hasAreaError) aria-invalid="true" aria-describedby="area-error-{{ $officer->id }}" @endif>
                                                <option value="" @selected($selectedArea === '' || $selectedArea === null)>Unassigned</option>
                                                @if ($selectedArea && !$areas->contains($selectedArea))
                                                    <option value="{{ $selectedArea }}" selected>{{ $selectedArea }}</option>
                                                @endif
                                                @foreach ($areas as $availableArea)
                                                    <option value="{{ $availableArea }}" @selected($selectedArea === $availableArea)>{{ $availableArea }}</option>
                                                @endforeach
                                            </select>
                                            @if ($hasAreaError)<span class="area-field-error" id="area-error-{{ $officer->id }}">{{ $errors->first('area') }}</span>@endif
                                        </div>
                                        <button type="submit" aria-label="Save area for {{ $officer->fullName }}">Save</button>
                                    </form>
                                @else
                                    <span class="area-table-muted">Contact your supervisor if this assignment needs to be changed.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->isOfficer() ? 4 : 3 }}" class="empty-state"><strong>{{ $search !== '' || $area !== '' ? 'No enforcers found' : 'No enforcers assigned yet' }}</strong><span>{{ $search !== '' || $area !== '' ? 'Try a different name or area.' : 'Enforcers will appear here when they are available for assignment.' }}</span>@if ($canManage && ($search !== '' || $area !== ''))<a class="page-button" href="{{ route('area-assignments.index', ['type' => $type]) }}">Clear filters</a>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Showing {{ $officers->firstItem() ?? 0 }}–{{ $officers->lastItem() ?? 0 }} of {{ number_format($officers->total()) }} enforcers</span>
            <nav class="pagination-actions" aria-label="Area assignment pages">
                @if ($officers->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Previous</span>@else<a class="page-button" href="{{ $officers->previousPageUrl() }}">Previous</a>@endif
                <span class="area-page-number">Page {{ $officers->currentPage() }} of {{ max(1, $officers->lastPage()) }}</span>
                @if ($officers->hasMorePages())<a class="page-button" href="{{ $officers->nextPageUrl() }}">Next</a>@else<span class="page-button is-disabled" aria-disabled="true">Next</span>@endif
            </nav>
        </div>
    </section>

    @if ($canManage)
        <dialog class="attendance-restriction-modal area-add-modal" id="addAreaModal" aria-labelledby="addAreaTitle" aria-describedby="addAreaDescription">
            <div class="attendance-modal-head">
                <div><h2 id="addAreaTitle">Add operational area</h2><p id="addAreaDescription">Create an area that can be selected when assigning enforcers.</p></div>
                <button type="button" data-close-area-modal aria-label="Close modal">&times;</button>
            </div>
            <form class="attendance-restriction-form" method="POST" action="{{ route('area-assignments.areas.store') }}" id="addAreaForm" data-area-save novalidate>
                @csrf
                <div class="form-field">
                    <label for="newAreaName">Area or location <span>Required</span></label>
                    <input id="newAreaName" name="name" type="text" maxlength="255" required value="{{ old('name') }}" placeholder="Enter area name" aria-describedby="newAreaError" @if ($errors->has('name')) aria-invalid="true" @endif>
                    <span class="area-field-error" id="newAreaError" @if (!$errors->has('name')) hidden @endif>{{ $errors->first('name') }}</span>
                </div>
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
    clearSearch.addEventListener('click', () => {
        search.value = '';
        clearSearch.hidden = true;
        search.focus();
        search.form.requestSubmit();
    });
    const modal = document.getElementById('addAreaModal');
    const opener = document.getElementById('openAddArea');
    const name = document.getElementById('newAreaName');
    const nameError = document.getElementById('newAreaError');
    opener.addEventListener('click', () => { modal.showModal(); name.focus(); });
    modal.querySelectorAll('[data-close-area-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
    modal.addEventListener('close', () => opener.focus());
    name.addEventListener('input', () => { name.removeAttribute('aria-invalid'); nameError.hidden = true; });
    document.querySelectorAll('[data-area-save]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (form.id === 'addAreaForm' && !name.value.trim()) {
            event.preventDefault();
            name.setAttribute('aria-invalid', 'true');
            nameError.textContent = 'Enter an area or location.';
            nameError.hidden = false;
            name.focus();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelector('button[type="submit"]').disabled = true;
    }));
    window.addEventListener('pageshow', () => document.querySelectorAll('[data-area-save]').forEach(form => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelector('button[type="submit"]').disabled = false;
    }));
    if (name.hasAttribute('aria-invalid')) { modal.showModal(); name.focus(); }
    document.querySelector('.area-assignment-field [aria-invalid="true"]')?.focus();
})();
</script>
@endif
@endpush
