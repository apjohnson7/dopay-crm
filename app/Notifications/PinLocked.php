<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class PinLocked extends Notification
{
    public function __construct(private User $owner) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => "Signing PIN for {$this->owner->name} can't be used to authorize other people's actions for 24 hours after 20 wrong attempts by colleagues. If this wasn't them, review the audit trail.",
            'url' => route('audit.index', ['q' => 'Wrong signing PIN']),
            'tone' => 'bad',
        ];
    }
}
