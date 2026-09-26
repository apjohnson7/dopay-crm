<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinanceForm;

/** Appendix F monitoring: approved annual budget vs actual paid expenses, by category. */
class BudgetMonitor
{
    public function __construct(private ExchangeRateService $rates) {}

    public function report(Country $country, int $year, int $month, bool $yearToDate): ?array
    {
        // JSON columns are compared in PHP so this works the same on MySQL, MariaDB and SQLite.
        $budget = FinanceForm::where('type', 'F')->where('country_id', $country->id)
            ->whereIn('status', ['approved', 'in_approval'])->orderByDesc('approved_at')->get()
            ->first(fn (FinanceForm $f) => $f->datum('period') === 'Annual' && (int) $f->datum('year') === $year);
        if (! $budget) {
            return null;
        }
        $months = $yearToDate ? range(1, $month) : [$month];
        $planned = $budget->budgetLines()->whereIn('month', $months)->get()->groupBy('expense_category_id')->map->sum('amount');

        $actual = [];
        Expense::where('country_id', $country->id)->whereIn('status', ['approved', 'paid'])->whereYear('spent_on', $year)->get()
            ->filter(fn (Expense $e) => in_array((int) $e->spent_on->format('n'), $months, true))
            ->each(function (Expense $e) use (&$actual, $country) {
                $actual[$e->expense_category_id] = ($actual[$e->expense_category_id] ?? 0) + $this->rates->convert((float) $e->amount, $e->currency_code, $country->currency_code, $e->spent_on);
            });

        $rows = ExpenseCategory::orderBy('position')->get()->map(fn ($c) => [
            'category' => $c, 'budget' => (float) ($planned[$c->id] ?? 0), 'actual' => round($actual[$c->id] ?? 0, 2),
        ]);

        return ['form' => $budget, 'rows' => $rows];
    }
}
