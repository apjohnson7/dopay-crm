<?php

namespace App\Services\Accounting;

use App\Models\BankStatementLine;
use App\Models\Country;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** Month-end close: a checklist, then a lock on everything dated in the month (see LedgerService::assertOpen). */
class PeriodCloseService
{
    public function __construct(private LedgerService $ledger) {}

    public function nextToClose(Country $country): string
    {
        return $country->books_closed_through
            ? Carbon::createFromFormat('Y-m-d', $country->books_closed_through.'-01')->addMonthNoOverflow()->format('Y-m')
            : Carbon::parse(\App\Models\JournalEntry::where('country_id', $country->id)->min('entry_date') ?? now())->format('Y-m');
    }

    /** @return array<int, array{label: string, detail: string, ok: bool, required: bool}> */
    public function checklist(Country $country, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01');
        [$from, $to] = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
        $bal = $this->ledger->balances($country, $to);
        $unbalanced = abs($bal->sum()) > 0.01;
        $waitingInv = Invoice::where('country_id', $country->id)->whereBetween('issue_date', [$from, $to])->whereIn('status', ['draft', 'pending_approval'])->count();
        $waitingExp = Expense::where('country_id', $country->id)->whereBetween('spent_on', [$from, $to])->whereIn('status', ['submitted', 'reviewed'])->count();
        $recon = LedgerAccount::where('country_id', $country->id)->whereIn('role', array_keys(config('accounting.reconcilable_roles')))->get()
            ->filter(fn ($a) => BankStatementLine::where('ledger_account_id', $a->id)->whereBetween('line_date', [$from, $to])->exists());
        $unconfirmed = $recon->reject(fn ($a) => Reconciliation::where('ledger_account_id', $a->id)->where('period', $period)->exists());

        return [
            ['label' => 'Trial balance balances', 'detail' => $unbalanced ? 'Debits and credits differ; contact support.' : 'Debits equal credits up to '.$start->copy()->endOfMonth()->format('j M Y').'.', 'ok' => ! $unbalanced, 'required' => true],
            ['label' => 'No invoices waiting', 'detail' => $waitingInv ? "{$waitingInv} draft or pending invoice(s) dated in the month." : 'All approved or cancelled.', 'ok' => ! $waitingInv, 'required' => true],
            ['label' => 'No expenses waiting', 'detail' => $waitingExp ? "{$waitingExp} expense(s) still in review." : 'All approved or paid.', 'ok' => ! $waitingExp, 'required' => true],
            ['label' => 'Bank and mobile money reconciled', 'detail' => $unconfirmed->count() ? 'Not confirmed: '.$unconfirmed->map->label()->implode(', ') : ($recon->count() ? 'All imported statements confirmed.' : 'No statements imported for the month (recommended, not required).'),
                'ok' => $unconfirmed->isEmpty(), 'required' => true],
        ];
    }

    public function close(Country $country, string $period, User $by): void
    {
        if ($period !== $this->nextToClose($country)) {
            throw ValidationException::withMessages(['period' => 'Close months in order: next is '.$this->nextToClose($country).'.']);
        }
        if (Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->isFuture()) {
            throw ValidationException::withMessages(['period' => 'A month can only be closed after it ends.']);
        }
        $missing = collect($this->checklist($country, $period))->filter(fn ($c) => $c['required'] && ! $c['ok']);
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['period' => 'Finish first: '.$missing->pluck('label')->implode(', ').'.']);
        }
        $country->update(['books_closed_through' => $period]);
        AuditLogger::log('Closed period', $country, null, ['period' => $period], 'DoPay '.$country->name.' '.$period);
    }

    /** Reopens the last closed month; the caller has already verified a second person's authorization. */
    public function reopen(Country $country, User $by, string $reason): void
    {
        $last = $country->books_closed_through ?? throw ValidationException::withMessages(['period' => 'No month is closed.']);
        $country->update(['books_closed_through' => Carbon::createFromFormat('Y-m-d', $last.'-01')->subMonthNoOverflow()->format('Y-m')]);
        AuditLogger::log('Reopened period', $country, ['closed_through' => $last], ['reason' => $reason], 'DoPay '.$country->name.' '.$last);
    }
}
