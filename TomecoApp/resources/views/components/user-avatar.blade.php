@props(['user', 'class' => 'table-avatar'])
<span {{ $attributes->class([$class, 'user-photo-avatar']) }}>
    <span>{{ collect(preg_split('/\s+/', $user->fullName))->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->join('') ?: 'U' }}</span>
    @if ($user->profile_photo_url)
        <img src="{{ $user->profile_photo_url }}" alt="" loading="lazy" onerror="this.remove()">
    @endif
</span>
