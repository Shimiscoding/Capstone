<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class AttendanceTimeOutUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $staffName,
        private readonly Carbon $attendanceDate,
        private readonly Carbon $timeIn,
        private readonly Carbon $timeOut,
        private readonly string $updatedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Attendance updated',
            'message' => $this->staffName.'\'s attendance for '.$this->attendanceDate->format('M d, Y').' was updated to '
                .$this->timeIn->format('h:i A').'–'.$this->timeOut->format('h:i A').' by '.$this->updatedBy.'.',
            'url' => route('dashboard', absolute: false),
        ];
    }
}
