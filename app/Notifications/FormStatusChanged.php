<?php

namespace App\Notifications;

use App\Models\FinanceForm;
use Illuminate\Notifications\Notification;

class FormStatusChanged extends Notification
{
    public function __construct(public FinanceForm $form, public string $what) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => "{$this->form->name()} {$this->form->reference} was {$this->what}",
            'url' => route('forms.show', $this->form),
            'tone' => str_contains($this->what, 'returned') ? 'bad' : 'ok',
        ];
    }
}
