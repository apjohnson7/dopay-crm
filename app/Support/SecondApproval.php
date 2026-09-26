<?php

namespace App\Support;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Sensitive actions (cancel invoice, reverse payment, revise a signed form, void) need a reason
 * and a second person's approval with their signing PIN.
 */
class SecondApproval
{
    public static function verify(Request $request, string $action, ?int $countryId = null, string $permission = 'invoices.approve'): User
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'authorizer_id' => ['required', 'integer', 'exists:users,id'],
            'authorizer_pin' => ['required', 'string'],
        ]);
        $authorizer = User::findOrFail($data['authorizer_id']);
        if (! $authorizer->is_active) {
            throw ValidationException::withMessages(['authorizer_id' => 'That account is not active.']);
        }
        if ($countryId && ! $authorizer->canActForCountry($countryId)) {
            throw ValidationException::withMessages(['authorizer_id' => 'The authorizer must work in the same country account.']);
        }
        if ($authorizer->id === $request->user()->id) {
            throw ValidationException::withMessages(['authorizer_id' => 'Someone other than you must authorize this.']);
        }
        if (! $authorizer->can($permission) && ! $authorizer->hasRole('Super Administrator')) {
            throw ValidationException::withMessages(['authorizer_id' => 'That person cannot authorize sensitive actions.']);
        }
        self::checkPin($authorizer, $data['authorizer_pin'], $request->user(), $action);
        AuditLogger::log("Authorized: {$action}", null, null, ['reason' => $data['reason'], 'authorized_by' => $authorizer->name], $action);

        return $authorizer;
    }

    /**
     * PIN check with two limits: 5 wrong tries per requester and target every 15 minutes (so one colleague
     * can't lock someone else out for long), and 20 per target a day, after which the PIN is locked and the
     * owner and Super Administrators are alerted.
     */
    public static function checkPin(User $owner, string $pin, User $requester, string $context): void
    {
        $self = $requester->is($owner);
        $pair = ($self ? 'pin-self:' : 'pin:'.$requester->id.':').$owner->id;
        $daily = 'pin-day:'.$owner->id; // wrong tries by other people only: nobody can freeze your own signing
        if (RateLimiter::tooManyAttempts($pair, 5) || (! $self && RateLimiter::tooManyAttempts($daily, 20))) {
            throw ValidationException::withMessages(['pin' => 'Too many wrong PINs. Try again later, or ask the PIN owner to reset it under Security.',
                'authorizer_pin' => 'Too many wrong PINs for this person. Try again later.']);
        }
        if (! $owner->checkSigningPin($pin)) {
            RateLimiter::hit($pair, 900);
            if (! $self) {
                RateLimiter::hit($daily, 86400);
            }
            AuditLogger::log('Wrong signing PIN', $owner, null, ['entered_by' => $requester->email], $context);
            if (! $self && RateLimiter::attempts($daily) === 20) {
                $admins = User::role('Super Administrator')->where('is_active', true)->get()->push($owner)->unique('id');
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\PinLocked($owner));
            }
            throw ValidationException::withMessages([$requester->is($owner) ? 'pin' : 'authorizer_pin' => 'That PIN is not correct.']);
        }
        RateLimiter::clear($pair);
    }

    /** People who may authorize a sensitive action in this country (never the requester). */
    public static function candidates(User $except, ?int $countryId = null, string $permission = 'invoices.approve')
    {
        return User::permission($permission)->where('id', '!=', $except->id)->where('is_active', true)->with('roles', 'branch')->orderBy('name')->get()
            ->filter(fn (User $u) => ! $countryId || $u->canActForCountry($countryId))->values();
    }
}
