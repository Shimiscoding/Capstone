@props(['name', 'label', 'type' => 'text', 'hint' => null, 'optional' => false, 'value' => null])
<div class="auth-field">
    <label for="{{ $name }}" class="auth-label">{{ $label }} @if ($optional)<small>(optional)</small>@endif</label>
    <div @class(['password-field' => $type === 'password'])>
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name, $value ?? ($name === 'login' ? old('email') : '')) }}" @endif
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            aria-describedby="{{ $name }}-error{{ $hint ? ' '.$name.'-hint' : '' }}"
            {{ $attributes->class(['auth-input']) }}>
        @if ($type === 'password')
            <button type="button" class="password-toggle" aria-controls="{{ $name }}" aria-label="Show {{ strtolower($label) }}"
                aria-pressed="false" data-password-toggle title="Show {{ strtolower($label) }}">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 6.2A12 12 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-3 3.7M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6c1.5 0 2.8-.3 4-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        @endif
    </div>
    @if ($hint)<p id="{{ $name }}-hint" class="auth-field-hint">{{ $hint }}</p>@endif
    <p id="{{ $name }}-error" class="auth-error" @if (!$errors->has($name)) hidden @endif>{{ $errors->first($name) }}</p>
</div>
