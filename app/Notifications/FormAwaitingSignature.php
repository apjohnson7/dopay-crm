<?php

namespace App\Notifications;

use App\Models\FinanceForm;
use Illuminate\Notifications\Notification;

class FormAwaitingSignature extends Notification
{
    public function __construct(public FinanceForm $form, public string $step) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => "{$this->form->name()} {$this->form->reference} awaits your signature ({$this->step})",
            'url' => route('forms.show', $this->form),
            'tone' => 'warn',
        ];
    }
}
