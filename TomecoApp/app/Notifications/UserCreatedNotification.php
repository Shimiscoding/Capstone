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
        return [
            'title' => 'New user registered',
            'message' => $this->createdUser->fullName.' was added as '.ucfirst($this->createdUser->role).'.',
            'url' => route('dashboard.users', ['search' => $this->createdUser->fullName]),
        ];
    }
}
