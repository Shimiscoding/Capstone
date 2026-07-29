@php
  $unreadCount = $user->unreadNotifications()->count();
  $dashboardNotifications = $user->notifications()->latest()->take(8)->get();
@endphp
<div class="notification-center">
  <button class="icon-button notification-toggle" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="notificationMenu">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M10 21h4"/></svg>
    @if ($unreadCount > 0)<span class="notification-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
  </button>
  <div class="notification-menu" id="notificationMenu" hidden>
    <div class="notification-header">
      <div><strong>Notifications</strong><small>{{ $unreadCount }} unread</small></div>
      @if ($unreadCount > 0)
        <form method="POST" action="{{ route('dashboard.notifications.read') }}">@csrf<button type="submit">Mark all as read</button></form>
      @endif
    </div>
    <div class="notification-list">
      @forelse ($dashboardNotifications as $notification)
        <form method="POST" action="{{ route('dashboard.notifications.read-one', $notification->id) }}">
          @csrf
          <button class="notification-item {{ $notification->read_at ? '' : 'is-unread' }}" type="submit">
            <i></i><span><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><small>{{ $notification->data['message'] ?? '' }}</small><time>{{ $notification->created_at->diffForHumans() }}</time></span>
          </button>
        </form>
      @empty
        <div class="notification-empty">You have no notifications yet.</div>
      @endforelse
    </div>
  </div>
</div>
<script>
  (() => {
    const center = document.currentScript.previousElementSibling;
    const toggle = center.querySelector('.notification-toggle');
    const menu = center.querySelector('.notification-menu');
    const close = () => { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', (event) => { event.stopPropagation(); menu.hidden = !menu.hidden; toggle.setAttribute('aria-expanded', String(!menu.hidden)); });
    menu.addEventListener('click', event => event.stopPropagation());
    document.addEventListener('click', close);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); toggle.focus(); } });
  })();
</script>
