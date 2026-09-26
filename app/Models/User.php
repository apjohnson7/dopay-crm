<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = ['name', 'email', 'phone', 'branch_id', 'job_title', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'signing_pin', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)->withPivot(['last_read_at', 'muted']);
    }

    public function country(): ?Country
    {
        return $this->branch?->country;
    }

    public function countryId(): ?int
    {
        return $this->branch?->country_id;
    }

    public function roleName(): string
    {
        return $this->getRoleNames()->first() ?? '—';
    }

    /** Only the Super Administrator works across country accounts; everyone else sees their own country's account. */
    public function isGlobal(): bool
    {
        return $this->hasAnyRole(config('dopay.global_roles'));
    }

    public function canActForCountry(int $countryId): bool
    {
        return $this->isGlobal() || $this->countryId() === $countryId;
    }

    /** Group approvers (FC, RM, CFO, CEO) may sign forms routed to them from any country. */
    public function isGroupApprover(): bool
    {
        return $this->hasAnyRole(config('dopay.group_approver_roles', []));
    }

    public function setSigningPin(string $pin): void
    {
        $this->forceFill(['signing_pin' => Hash::make($pin), 'pin_set_at' => now()])->save();
    }

    public function checkSigningPin(?string $pin): bool
    {
        return $pin !== null && $this->signing_pin !== null && Hash::check($pin, $this->signing_pin);
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    }
}
