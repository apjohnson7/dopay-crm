<?php

namespace App\Services;

use App\Models\FinanceForm;
use App\Models\FormSignature;
use App\Models\User;
use App\Notifications\FormAwaitingSignature;
use App\Notifications\FormStatusChanged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The approval chains from the policy templates. Each form is signed step by step, in order,
 * by someone whose role and country fit that step.
 */
class ApprovalService
{
    public function steps(FinanceForm $form): array
    {
        $def = config('dopay.forms.'.$form->type);
        if (isset($def['routes'])) {
            return $def['routes'][$form->datum('route', 'CFO')]['steps'];
        }

        return $def['steps'];
    }

    /** Index of the next step to sign, or null when the chain is complete / not running. */
    public function currentStep(FinanceForm $form): ?int
    {
        if ($form->status !== 'in_approval') {
            return null;
        }
        $signed = $form->signatures()->where('version', $form->version)->where('step_index', '>=', 0)->pluck('step_index')->all();
        foreach (array_keys($this->steps($form)) as $i) {
            if (! in_array($i, $signed, true)) {
                return $i;
            }
        }

        return null;
    }

    public function canSign(User $user, FinanceForm $form, int $stepIndex): bool
    {
        $step = $this->steps($form)[$stepIndex] ?? null;
        if (! $step || ! $user->is_active) {
            return false;
        }
        if (isset($step['who'])) {
            return match ($step['who']) {
                'preparer' => $user->id === $form->prepared_by,
                'holder' => $user->id === (int) $form->datum('holder_id'),
                'purchaser' => $user->id === (int) $form->datum('purchaser_id'),
            };
        }
        if (! $user->hasAnyRole($step['roles'])) {
            return false;
        }
        $countryId = ! empty($step['paying']) ? (int) $form->datum('paying_country_id') : $form->country_id;
        if (! $user->isGlobal() && ! $user->isGroupApprover() && $user->countryId() !== $countryId) {
            return false;
        }
        if (! empty($step['not_holder']) && $user->id === (int) $form->datum('holder_id')) {
            return false;
        }

        return true;
    }

    public function eligibleSigners(FinanceForm $form, int $stepIndex): Collection
    {
        return User::where('is_active', true)->with('branch', 'roles')->get()
            ->filter(fn (User $u) => $this->canSign($u, $form, $stepIndex))->values();
    }

    public function awaiting(User $user): Collection
    {
        return FinanceForm::where('status', 'in_approval')->with('signatures', 'country', 'branch')->get()
            ->filter(function (FinanceForm $f) use ($user) {
                $i = $this->currentStep($f);

                return $i !== null && $this->canSign($user, $f, $i);
            })->values();
    }

    public function submit(FinanceForm $form, User $user): void
    {
        if (! in_array($form->status, ['draft', 'returned'], true)) {
            throw ValidationException::withMessages(['status' => 'Only drafts and returned forms can be submitted.']);
        }
        DB::transaction(function () use ($form, $user) {
            $form->update(['status' => 'in_approval', 'submitted_at' => now(), 'return_note' => null]);
            if (! empty(config('dopay.forms.'.$form->type.'.requester_signature'))) {
                $this->writeSignature($form, $user, -1, 'Requester', null);
            }
            // Steps the submitter is entitled to (preparer / holder / purchaser) are signed straight away.
            while (($i = $this->currentStep($form)) !== null && isset($this->steps($form)[$i]['who']) && $this->canSign($user, $form, $i)) {
                $this->writeSignature($form, $user, $i, $this->steps($form)[$i]['label'], null);
            }
            $this->afterSignature($form);
        });
    }

    public function sign(FinanceForm $form, User $user, string $pin, ?string $comment = null): void
    {
        if (! $user->checkSigningPin($pin)) {
            throw ValidationException::withMessages(['pin' => 'That PIN is not correct.']);
        }
        $i = $this->currentStep($form);
        if ($i === null || ! $this->canSign($user, $form, $i)) {
            abort(403, 'This step is not yours to sign.');
        }
        DB::transaction(function () use ($form, $user, $i, $comment) {
            $this->writeSignature($form, $user, $i, $this->steps($form)[$i]['label'], $comment);
            $this->afterSignature($form);
        });
    }

    public function returnToPreparer(FinanceForm $form, User $user, string $note): void
    {
        $i = $this->currentStep($form);
        if ($i === null || ! $this->canSign($user, $form, $i)) {
            abort(403);
        }
        $form->update(['status' => 'returned', 'return_note' => $note.' — '.$user->name, 'version' => $form->version + 1]);
        $form->preparer->notify(new FormStatusChanged($form, 'returned for correction: '.$note));
    }

    private function writeSignature(FinanceForm $form, User $user, int $stepIndex, string $label, ?string $comment): void
    {
        $country = $form->country;
        FormSignature::create([
            'finance_form_id' => $form->id,
            'version' => $form->version,
            'step_index' => $stepIndex,
            'step_label' => $label,
            'user_id' => $user->id,
            'signer_role' => $user->roleName(),
            'signed_at' => now(),
            'local_time' => ($user->country() ?? $country)->localTime(),
            'code' => strtoupper(Str::random(8)),
            'comment' => $comment,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? 'console' : substr((string) request()->userAgent(), 0, 250),
        ]);
        AuditLogger::log('Signed '.$form->name().': '.$label, $form);
    }

    private function afterSignature(FinanceForm $form): void
    {
        $next = $this->currentStep($form);
        if ($next === null) {
            $form->update(['status' => 'approved', 'approved_at' => now()]);
            $form->preparer->notify(new FormStatusChanged($form, 'fully approved'));

            return;
        }
        Notification::send($this->eligibleSigners($form, $next), new FormAwaitingSignature($form, $this->steps($form)[$next]['label']));
    }
}
