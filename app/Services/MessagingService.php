<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewTeamMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class MessagingService
{
    public function send(Conversation $conversation, User $from, string $body, ?Model $attach = null): Message
    {
        abort_unless($conversation->users()->where('users.id', $from->id)->exists(), 403);

        $message = $conversation->messages()->create([
            'user_id' => $from->id,
            'body' => trim($body),
            'attachable_type' => $attach?->getMorphClass(),
            'attachable_id' => $attach?->getKey(),
        ]);
        $conversation->update(['last_message_at' => now()]);
        $conversation->users()->updateExistingPivot($from->id, ['last_read_at' => now()]);

        $recipients = $conversation->users()->where('users.id', '!=', $from->id)->wherePivot('muted', false)->get();
        // Mentions (@First Last) always notify, even in muted conversations.
        preg_match_all('/@([\p{Lu}][\w\'-]+(?: [\p{Lu}][\w\'-]+)?)/u', $body, $m);
        $mentioned = $m[1] ? $conversation->users()->whereIn('name', $m[1])->get() : collect();
        Notification::send($recipients->merge($mentioned)->unique('id'), new NewTeamMessage($message));

        return $message;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return $user->conversations()->get()->sum(fn (Conversation $c) => $c->unreadFor($user));
    }
}
