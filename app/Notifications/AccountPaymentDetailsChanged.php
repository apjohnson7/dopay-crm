<?php

namespace App\Notifications;

use App\Models\Country;
use App\Models\User;
use Illuminate\Notifications\Notification;

class AccountPaymentDetailsChanged extends Notification
{
    public function __construct(private Country $country, private User $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => "Bank or mobile money details on DoPay {$this->country->name} invoices were changed by {$this->by->name}. Check they are correct.",
            'url' => route('account.edit', ['country' => $this->country->id]),
            'tone' => 'warn',
        ];
    }
}
