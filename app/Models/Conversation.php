<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'country_id', 'created_by', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['last_read_at', 'muted']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function titleFor(User $viewer): string
    {
        if ($this->type === 'direct') {
            return $this->users->firstWhere('id', '!=', $viewer->id)?->name ?? 'Conversation';
        }

        return $this->name ?? 'Group';
    }

    public function otherUser(User $viewer): ?User
    {
        return $this->type === 'direct' ? $this->users->firstWhere('id', '!=', $viewer->id) : null;
    }

    public function unreadFor(User $viewer): int
    {
        $pivot = $this->users->firstWhere('id', $viewer->id)?->pivot;
        $q = $this->messages()->where('user_id', '!=', $viewer->id);
        if ($pivot?->last_read_at) {
            $q->where('created_at', '>', $pivot->last_read_at);
        }

        return $q->count();
    }

    /** Find the one-to-one conversation between two users, or start it. */
    public static function directBetween(User $a, User $b): self
    {
        $existing = static::where('type', 'direct')
            ->whereHas('users', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $b->id))
            ->first();
        if ($existing) {
            return $existing;
        }
        $c = static::create(['type' => 'direct', 'created_by' => $a->id]);
        $c->users()->attach([$a->id, $b->id]);

        return $c;
    }
}
