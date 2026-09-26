<?php

namespace App\Support;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sensitive actions (cancel invoice, reverse payment, revise a signed form, void) need a reason
 * and a second person's approval with their signing PIN.
 */
class SecondApproval
{
    public static function verify(Request $request, string $action): User
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'authorizer_id' => ['required', 'integer', 'exists:users,id'],
            'authorizer_pin' => ['required', 'string'],
        ]);
        $authorizer = User::findOrFail($data['authorizer_id']);
        if ($authorizer->id === $request->user()->id) {
            throw ValidationException::withMessages(['authorizer_id' => 'Someone other than you must authorize this.']);
        }
        if (! $authorizer->can('invoices.approve') && ! $authorizer->hasRole('Super Administrator')) {
            throw ValidationException::withMessages(['authorizer_id' => 'That person cannot authorize sensitive actions.']);
        }
        if (! $authorizer->checkSigningPin($data['authorizer_pin'])) {
            throw ValidationException::withMessages(['authorizer_pin' => 'The authorizer PIN is not correct.']);
        }
        AuditLogger::log("Authorized: {$action}", null, null, ['reason' => $data['reason'], 'authorized_by' => $authorizer->name], $action);

        return $authorizer;
    }

    public static function candidates(User $except)
    {
        return User::permission('invoices.approve')->where('id', '!=', $except->id)->where('is_active', true)->orderBy('name')->get();
    }
}
