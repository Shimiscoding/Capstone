<?php

namespace App\Notifications;

use App\Models\User;
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
        $routeName = match ($this->userRole) {
            User::ROLE_OFFICER => 'dashboard.users.enforcers',
            User::ROLE_ADMIN => 'dashboard.users.admins',
            default => 'dashboard.users.supervisors',
        };

        return [
            'title' => $wasDeleted ? 'User account deleted' : 'User account updated',
            'message' => $this->userName.' ('.ucfirst($this->userRole).') was '.$this->action.'.',
            'url' => $wasDeleted
                ? route($routeName, absolute: false)
                : route($routeName, ['search' => $this->userName], false),
        ];
    }
}
