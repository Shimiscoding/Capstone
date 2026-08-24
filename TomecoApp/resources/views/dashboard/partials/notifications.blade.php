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
    @if ($dashboardNotifications->isNotEmpty())
    
      <div class="notification-actions">
        <br>
        <label><input class="notification-select-all" type="checkbox"> Select all</label>
        <form id="deleteSelectedNotifications" method="POST" action="{{ route('dashboard.notifications.delete') }}" onsubmit="return confirm('Delete the selected notifications?')">@csrf @method('DELETE')<button type="submit" disabled>Delete selected</button></form>
        <form method="POST" action="{{ route('dashboard.notifications.delete-all') }}" onsubmit="return confirm('Delete all notifications? This cannot be undone.')">@csrf @method('DELETE')<button class="delete-all-notifications" type="submit">Delete all</button></form>
      </div>
    @endif
    <div class="notification-list">
      @forelse ($dashboardNotifications as $notification)
        <div class="notification-row">
          <input class="notification-select" type="checkbox" name="notifications[]" value="{{ $notification->id }}" form="deleteSelectedNotifications" aria-label="Select {{ $notification->data['title'] ?? 'notification' }}"> <br>
          <form method="POST" action="{{ route('dashboard.notifications.read-one', $notification->id) }}">
          @csrf
          
          <button class="notification-item {{ $notification->read_at ? '' : 'is-unread' }}" type="submit">
            <i></i><span><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><small>{{ $notification->data['message'] ?? '' }}</small><time>{{ $notification->created_at->diffForHumans() }}</time></span>
          </button>
          </form>
        </div>
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
    const notificationCheckboxes = [...center.querySelectorAll('.notification-select')];
    const selectAll = center.querySelector('.notification-select-all');
    const deleteSelected = center.querySelector('#deleteSelectedNotifications button');
    const updateSelection = () => {
      const selectedCount = notificationCheckboxes.filter(checkbox => checkbox.checked).length;
      if (deleteSelected) { deleteSelected.disabled = selectedCount === 0; deleteSelected.textContent = selectedCount ? `Delete selected (${selectedCount})` : 'Delete selected'; }
      if (selectAll) { selectAll.checked = selectedCount === notificationCheckboxes.length; selectAll.indeterminate = false; }
    };
    notificationCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateSelection));
    selectAll?.addEventListener('change', () => { notificationCheckboxes.forEach(checkbox => { checkbox.checked = selectAll.checked; }); updateSelection(); });
    document.addEventListener('click', close);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); toggle.focus(); } });
  })();
</script>
