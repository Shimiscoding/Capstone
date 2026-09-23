<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $createdUser)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $routeName = match ($this->createdUser->role) {
            User::ROLE_OFFICER => 'dashboard.users.enforcers',
            User::ROLE_ADMIN => 'dashboard.users.admins',
            default => 'dashboard.users.supervisors',
        };

        return [
            'title' => 'New user registered',
            'message' => $this->createdUser->fullName.' was added as '.ucfirst($this->createdUser->role).'.',
            'url' => route($routeName, ['search' => $this->createdUser->fullName], false),
        ];
    }
}
