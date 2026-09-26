<?php

namespace App\Notifications;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Notifications\Notification;

class SupplierBankChanged extends Notification
{
    public function __construct(private Supplier $supplier, private User $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => "Bank details changed for supplier {$this->supplier->company} ({$this->supplier->code}) by {$this->by->name}",
            'url' => route('suppliers.edit', $this->supplier),
            'tone' => 'warn',
        ];
    }
}
