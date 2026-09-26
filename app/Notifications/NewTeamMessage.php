<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewTeamMessage extends Notification
{
    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => $this->message->user->name.': '.Str::limit($this->message->body, 80),
            'url' => route('messages.index', ['c' => $this->message->conversation_id]),
            'tone' => 'info',
        ];
    }
}
