<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $action,
        private readonly string $userName,
        private readonly string $userRole,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $wasDeleted = $this->action === 'deleted';

        return [
            'title' => $wasDeleted ? 'User account deleted' : 'User account updated',
            'message' => $this->userName.' ('.ucfirst($this->userRole).') was '.$this->action.'.',
            'url' => $wasDeleted
                ? route('dashboard.users', absolute: false)
                : route('dashboard.users', ['search' => $this->userName], false),
        ];
    }
}
