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
            <h1>{{ $canManage ? 'Enforcer Area Assignments' : 'My Assigned Areas' }} <span class="users-heading-count">{{ number_format($officers->total()) }}</span></h1>
            <p class="page-description">{{ $canManage ? 'Assign up to '.$maxAreas.' duty areas per enforcer.' : 'View your assigned duty areas.' }}</p>
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
        <p class="area-table-hint">Scroll horizontally to see all columns.</p>
        <div class="table-scroll" role="region" aria-label="Enforcer area assignments table" tabindex="0">
            <table class="users-table area-assignment-table">
                <caption class="sr-only">Enforcers and their current operational area assignments</caption>
                <thead><tr><th scope="col">Enforcer</th>@if(auth()->user()->isOfficer())<th scope="col">Supervisor / Team</th>@endif<th scope="col">Current areas</th><th scope="col">{{ $canManage ? 'Actions' : 'Assignment status' }}</th></tr></thead>
                <tbody>
                    @forelse ($officers as $officer)
                        @php
                            $assignedAreas = $officer->assignedAreas();
                        @endphp
                        <tr>
                            <td><div class="user-cell"><x-user-avatar :user="$officer" /><span><strong>{{ $officer->fullName }}</strong><small>{{ $officer->username ? '@'.$officer->username : $officer->email }}</small></span></div></td>
                            @if(auth()->user()->isOfficer())
                                <td><strong>{{ $officer->supervisor?->fullName ?? 'Unassigned' }}</strong><small class="area-table-muted">{{ $officer->supervisor ? 'Supervisor-led team' : 'No team assigned' }}</small></td>
                            @endif
                            <td>
                                @if ($assignedAreas)
                                    <ul class="area-current-list" role="list">
                                        @foreach ($assignedAreas as $assignedArea)
                                            <li class="area-current">{{ $assignedArea }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="area-current is-unassigned">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                @if ($canManage)
                                    <button type="button" class="page-button area-edit-button" aria-haspopup="dialog"
                                        aria-label="Edit areas for {{ $officer->fullName }}"
                                        data-edit-areas="{{ $officer->id }}" data-enforcer-name="{{ $officer->fullName }}"
                                        data-update-url="{{ route('area-assignments.update', $officer) }}"
                                        data-assigned-areas="{{ json_encode($assignedAreas, JSON_INVALID_UTF8_SUBSTITUTE) }}">
                                        Edit areas
                                    </button>
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
        @php
            $retryId = old('assignment_user_id');
            $retryEnforcer = is_scalar($retryId) && $errors->hasAny(['areas', 'areas.*'])
                ? $officers->firstWhere('id', $retryId)
                : null;
        @endphp
        <dialog class="attendance-restriction-modal area-modal" id="editAreaModal" aria-labelledby="editAreaTitle" aria-describedby="editAreaDescription"
            @if ($retryEnforcer)
                data-retry-enforcer="{{ $retryEnforcer->id }}"
                data-retry-areas="{{ json_encode(old('areas'), JSON_INVALID_UTF8_SUBSTITUTE) }}"
            @endif>
            <div class="attendance-modal-head">
                <div>
                    <h2 id="editAreaTitle" tabindex="-1">Edit duty areas</h2>
                    <p id="editAreaDescription">Assign up to {{ $maxAreas }} distinct areas to <strong id="editAreaEnforcer"></strong>. Leave unused slots blank.</p>
                </div>
                <button type="button" data-close-area-modal aria-label="Close edit areas">&times;</button>
            </div>
            <form class="attendance-restriction-form area-assignment-form" id="editAreaForm" method="POST" data-area-save novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="assignment_user_id">
                <div class="area-assignment-fields">
                    @for ($index = 0; $index < $maxAreas; $index++)
                        @php
                            $fieldId = 'duty-area-'.$index;
                            $fieldError = $retryEnforcer
                                ? (($index === 0 ? $errors->first('areas') : '') ?: $errors->first('areas.'.$index))
                                : '';
                        @endphp
                        <div class="area-assignment-field">
                            <label for="{{ $fieldId }}">Duty area {{ $index + 1 }}</label>
                            <div>
                                <select id="{{ $fieldId }}" name="areas[]" @if ($fieldError) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
                                    <option value="">No area selected</option>
                                    @foreach ($areas as $availableArea)
                                        <option value="{{ $availableArea }}">{{ $availableArea }}</option>
                                    @endforeach
                                </select>
                                @if ($fieldError)
                                    <span class="area-field-error" id="{{ $fieldId }}-error">{{ $fieldError }}</span>
                                @endif
                            </div>
                        </div>
                    @endfor
                </div>
                <div class="attendance-modal-actions">
                    <button type="button" class="page-button" data-close-area-modal>Cancel</button>
                    <button type="submit">Save areas</button>
                </div>
            </form>
        </dialog>

        <dialog class="attendance-restriction-modal area-modal area-add-modal" id="addAreaModal" aria-labelledby="addAreaTitle" aria-describedby="addAreaDescription">
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
    modal.addEventListener('close', () => opener.focus());
    name.addEventListener('input', () => { name.removeAttribute('aria-invalid'); nameError.hidden = true; });

    const editModal = document.getElementById('editAreaModal');
    const editForm = document.getElementById('editAreaForm');
    const editTitle = document.getElementById('editAreaTitle');
    const enforcer = document.getElementById('editAreaEnforcer');
    const editButtons = Array.from(document.querySelectorAll('[data-edit-areas]'));
    let editOpener;
    function openEditor(button, retry = false) {
        editOpener = button;
        editForm.action = button.dataset.updateUrl;
        editForm.elements.assignment_user_id.value = button.dataset.editAreas;
        enforcer.textContent = button.dataset.enforcerName;
        const saved = JSON.parse(button.dataset.assignedAreas);
        const selected = retry ? JSON.parse(editModal.dataset.retryAreas) : saved;

        editForm.querySelectorAll('select').forEach((select, index) => {
            select.querySelectorAll('[data-saved-option]').forEach(option => option.remove());
            saved.forEach(area => {
                if (Array.from(select.options).some(option => option.value === area)) return;
                const option = new Option(area, area);
                option.setAttribute('data-saved-option', '');
                select.add(option);
            });
            select.value = Array.isArray(selected) ? (selected[index] ?? '') : '';
            if (select.selectedIndex === -1) select.selectedIndex = 0;
        });
        editModal.showModal();
        (editForm.querySelector('[aria-invalid="true"]') ?? editTitle).focus();
    }
    function clearEditErrors() {
        editForm.querySelectorAll('[aria-invalid]').forEach(select => {
            select.removeAttribute('aria-invalid');
            select.removeAttribute('aria-describedby');
        });
        editForm.querySelectorAll('.area-field-error').forEach(error => { error.hidden = true; });
    }
    editButtons.forEach(button => button.addEventListener('click', () => openEditor(button)));
    editForm.addEventListener('change', clearEditErrors);
    editModal.addEventListener('close', () => {
        clearEditErrors();
        editOpener?.focus();
    });
    [modal, editModal].forEach(dialog => {
        dialog.querySelectorAll('[data-close-area-modal]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    });
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
    const retryButton = editButtons.find(button => button.dataset.editAreas === editModal.dataset.retryEnforcer);
    if (retryButton) {
        openEditor(retryButton, true);
    } else if (name.hasAttribute('aria-invalid')) {
        modal.showModal();
        name.focus();
    }
})();
</script>
@endif
@endpush
