@props(['user' => null])
<div class="form-field photo-editor" data-photo-editor>
    <span class="photo-editor-avatar user-photo-avatar"><span>{{ $user ? collect(preg_split('/\s+/', $user->fullName))->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->join('') : 'U' }}</span>
        <img data-photo-preview src="{{ $user?->profile_photo_url ?: 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=' }}" alt="Profile photo preview" @if (!$user?->profile_photo_url) hidden @endif>
    </span>
    <label class="sr-only" for="profile_photo">Profile photo</label>
    <input class="sr-only" id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profilePhotoHelp" tabindex="-1">
    <input type="hidden" name="remove_profile_photo" value="0" data-photo-remove>
    <div class="photo-editor-actions"><button type="button" class="photo-upload-button" data-photo-choose>{{ $user?->profile_photo_url ? 'Change photo' : 'Upload photo' }}</button><button type="button" class="photo-remove-button" data-photo-clear @if (!$user?->profile_photo_url) hidden @endif>Remove</button></div>
    <small id="profilePhotoHelp" class="photo-editor-help">JPG, PNG or WebP · Up to 2 MB</small>
    <small class="photo-editor-feedback" data-photo-feedback role="status" aria-live="polite"></small>
    @error('profile_photo')<small class="field-error">{{ $message }}</small>@enderror
    @error('remove_profile_photo')<small class="field-error">{{ $message }}</small>@enderror
    <noscript><style>.photo-editor input[type=file]{position:static;width:100%;height:auto;clip:auto}</style></noscript>
</div>
@once
@push('scripts')
<script>
document.querySelectorAll('[data-photo-editor]').forEach(editor => {
    const input = editor.querySelector('input[type=file]'), preview = editor.querySelector('[data-photo-preview]');
    const remove = editor.querySelector('[data-photo-remove]'), clear = editor.querySelector('[data-photo-clear]');
    const feedback = editor.querySelector('[data-photo-feedback]');
    const originalPhoto = preview.hidden ? '' : preview.src;
    let previewUrl;
    const release = () => { if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; } };
    editor.querySelector('[data-photo-choose]').addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const file = input.files[0]; if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            release(); input.value = ''; preview.src = originalPhoto; preview.hidden = !originalPhoto; clear.hidden = !originalPhoto; remove.value = '0'; feedback.textContent = 'Choose a JPG, PNG or WebP image under 2 MB.'; feedback.classList.add('is-error'); return;
        }
        release(); previewUrl = URL.createObjectURL(file); preview.src = previewUrl; preview.hidden = false;
        clear.hidden = false; remove.value = '0'; feedback.classList.remove('is-error'); feedback.textContent = 'New photo selected. Save to apply.';
    });
    preview.addEventListener('error', () => { preview.hidden = true; });
    clear.addEventListener('click', () => {
        release(); input.value = ''; preview.hidden = true; remove.value = '1'; clear.hidden = true;
        feedback.classList.remove('is-error'); feedback.textContent = 'Photo removed. Save to apply.';
        editor.querySelector('[data-photo-choose]').focus();
    });
    window.addEventListener('pagehide', release);
});
</script>
@endpush
@endonce
