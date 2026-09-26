<?php

namespace App\Services\Accounting;

use App\Models\Country;
use App\Models\LedgerAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Trial balance, profit and loss and balance sheet straight from the ledger. */
class FinancialStatements
{
    public function __construct(private LedgerService $ledger) {}

    private function accounts(Country $country): Collection
    {
        return LedgerAccount::where('country_id', $country->id)->orderBy('code')->get()->keyBy('id');
    }

    /** Rows of [account, debit, credit] at a date. */
    public function trialBalance(Country $country, $to): Collection
    {
        $bal = $this->ledger->balances($country, $to);

        return $this->accounts($country)->map(fn (LedgerAccount $a) => ['account' => $a, 'debit' => max(0, $bal[$a->id] ?? 0), 'credit' => max(0, -($bal[$a->id] ?? 0))])
            ->filter(fn ($r) => $r['debit'] > 0.004 || $r['credit'] > 0.004)->values();
    }

    /** Income and expense grouped by statement line for a period; 'net' is profit (positive) or loss. */
    public function profitAndLoss(Country $country, $from, $to): array
    {
        $bal = $this->ledger->balances($country, $to, $from);
        $rows = $this->accounts($country)->filter(fn ($a) => in_array($a->type, ['income', 'expense'], true))
            ->map(fn (LedgerAccount $a) => ['account' => $a, 'amount' => $a->natural($bal[$a->id] ?? 0)])->filter(fn ($r) => abs($r['amount']) > 0.004);
        $income = $rows->filter(fn ($r) => $r['account']->type === 'income');
        $expense = $rows->filter(fn ($r) => $r['account']->type === 'expense');

        return ['income' => $income->groupBy(fn ($r) => $r['account']->statement_line), 'expense' => $expense->groupBy(fn ($r) => $r['account']->statement_line),
            'total_income' => $income->sum('amount'), 'total_expense' => $expense->sum('amount'), 'net' => $income->sum('amount') - $expense->sum('amount')];
    }

    /** Assets = liabilities + equity, with this year's result shown inside equity. */
    public function balanceSheet(Country $country, $at): array
    {
        $bal = $this->ledger->balances($country, $at);
        $yearStart = Carbon::parse($at)->startOfYear()->toDateString();
        $pl = $this->profitAndLoss($country, $yearStart, $at);
        $earlier = $this->ledger->balances($country, Carbon::parse($yearStart)->subDay()->toDateString());
        $accounts = $this->accounts($country);
        $prior = -$accounts->filter(fn ($a) => in_array($a->type, ['income', 'expense'], true))->sum(fn ($a) => $earlier[$a->id] ?? 0);
        $side = fn (string $type) => $accounts->filter(fn ($a) => $a->type === $type)->map(fn ($a) => ['account' => $a, 'amount' => $a->natural($bal[$a->id] ?? 0)])
            ->filter(fn ($r) => abs($r['amount']) > 0.004)->groupBy(fn ($r) => $r['account']->statement_line);
        $assets = $side('asset');
        $liabilities = $side('liability');
        $equity = $side('equity');
        $totalEquity = $equity->flatten(1)->sum('amount') + $prior + $pl['net'];

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'prior_results' => $prior, 'current_result' => $pl['net'],
            'total_assets' => $assets->flatten(1)->sum('amount'), 'total_liabilities' => $liabilities->flatten(1)->sum('amount'), 'total_equity' => $totalEquity];
    }
}
