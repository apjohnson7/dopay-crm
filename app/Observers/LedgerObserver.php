<?php

namespace App\Observers;

use App\Models\Expense;
use App\Models\FinanceForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Accounting\PostingRules;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts business events to the ledger as they happen. Runs inside the same database transaction as the
 * change that triggered it (the services wrap their work in transactions), so a record and its journal entry
 * are saved together or not at all — for example, approving an invoice dated in a closed month fails whole.
 */
class LedgerObserver
{
    public function __construct(private PostingRules $rules) {}

    public function saved(Model $model): void
    {
        if (! $model->wasRecentlyCreated && ! $model->wasChanged('status')) {
            return;
        }
        match (true) {
            $model instanceof Invoice => $model->status === 'cancelled' ? $this->rules->invoiceCancelled($model) : $this->rules->invoice($model),
            $model instanceof Payment => $model->status === 'reversed' ? $this->rules->paymentReversed($model) : $this->rules->payment($model),
            $model instanceof Expense => $this->rules->expense($model),
            $model instanceof FinanceForm => match ($model->type) { 'B' => $this->rules->pettyCash($model), 'K' => $this->rules->advance($model), 'J' => $this->rules->interbranch($model), default => null },
            default => null,
        };
    }
}
