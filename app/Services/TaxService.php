<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Connects tax on both sides of the books:
 *  - VAT/TVA charged = tax on approved (official) invoices, by month of issue;
 *  - VAT/TVA remitted = paid expenses in the "Tax" category whose description names VAT/TVA,
 *    matched to the period in tax_period (or the month before payment when blank);
 *  - other taxes (PAYE, withholding, ...) = the remaining Tax-category expenses.
 */
class TaxService
{
    public const VAT_PATTERN = '/\b(VAT|TVA)\b/i';

    /** Invoice statuses that count as official, taxable supplies. */
    public const LIVE_INVOICE = ['approved', 'sent'];

    public function __construct(private NumberingService $numbers, private ExchangeRateService $rates) {}

    public static function taxCategoryId(): ?int
    {
        return ExpenseCategory::where('name', 'Tax')->value('id');
    }

    public static function isVat(Expense $e): bool
    {
        return (bool) preg_match(self::VAT_PATTERN, (string) $e->description);
    }

    public static function periodOf(Expense $e): string
    {
        return $e->tax_period ?: Carbon::parse($e->spent_on)->subMonthNoOverflow()->format('Y-m');
    }

    public static function dueDate(Country $country, string $period): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $period.'-01')->addMonthNoOverflow()->day(min(28, max(1, (int) ($country->vat_filing_day ?: 15))));
    }

    public function taxPayments(Country $country): Collection
    {
        return Expense::with('branch', 'submitter')
            ->where('country_id', $country->id)
            ->where('expense_category_id', self::taxCategoryId())
            ->where('status', '!=', 'rejected')
            ->orderByDesc('spent_on')->get();
    }

    /** Monthly VAT/TVA returns, newest first. */
    public function periods(Country $country, ?Carbon $today = null): Collection
    {
        $today ??= now($country->timezone);
        $thisMonth = $today->format('Y-m');

        $invoices = Invoice::with('customer')
            ->where('country_id', $country->id)
            ->whereIn('status', self::LIVE_INVOICE)
            ->orderBy('issue_date')->get();
        $vatPays = $this->taxPayments($country)->filter(fn ($e) => self::isVat($e));

        $periods = $invoices->map(fn ($i) => Carbon::parse($i->issue_date)->format('Y-m'))
            ->merge($vatPays->map(fn ($e) => self::periodOf($e)))
            ->push($thisMonth)->unique()->sortDesc()->values();

        return $periods->map(function (string $p) use ($invoices, $vatPays, $country, $today, $thisMonth) {
            $inv = $invoices->filter(fn ($i) => Carbon::parse($i->issue_date)->format('Y-m') === $p)->values();
            $net = (float) $inv->sum(fn ($i) => $i->subtotal - $i->discount_total);
            $vat = (float) $inv->sum('tax_total');
            $pays = $vatPays->filter(fn ($e) => self::periodOf($e) === $p);
            $paid = (float) $pays->where('status', 'paid')->sum('amount');
            $pending = (float) $pays->where('status', '!=', 'paid')->sum('amount');
            $balance = round($vat - $paid, 2);
            $due = self::dueDate($country, $p);
            $status = match (true) {
                $p >= $thisMonth => 'Open period',
                $balance <= 0 => 'Paid',
                $pending > 0 => 'In approval',
                $due->lt($today->copy()->startOfDay()) => 'Overdue',
                default => 'Due',
            };

            return ['period' => $p, 'label' => Carbon::createFromFormat('Y-m-d', $p.'-01')->format('F Y'), 'invoices' => $inv,
                'net' => round($net, 2), 'vat' => round($vat, 2), 'paid' => round($paid, 2), 'pending' => round($pending, 2),
                'balance' => $balance, 'due' => $due, 'status' => $status];
        });
    }

    /** Returns that need attention within $days (or are overdue) for the dashboard and reminders. */
    public function alerts(Collection $countries, int $days = 21): Collection
    {
        return $countries->map(function (Country $c) use ($days) {
            $today = now($c->timezone)->startOfDay();
            $row = $this->periods($c)->filter(fn ($r) => $r['balance'] > 0 && (in_array($r['status'], ['Overdue', 'In approval'], true) || $today->diffInDays($r['due'], false) <= $days))
                ->sortBy(fn ($r) => $r['due']->timestamp)->first();

            return $row ? ['country' => $c, 'row' => $row] : null;
        })->filter()->values();
    }

    /** Record a tax payment as a Tax-category expense. It counts as remitted once it is marked paid. */
    public function record(Country $country, Branch $branch, array $data, User $by): Expense
    {
        abort_unless($branch->country_id === $country->id, 422, 'The branch must belong to the same country.');

        return DB::transaction(fn () => Expense::create([
            'number' => $this->numbers->document($country, 'EXP'),
            'country_id' => $country->id,
            'branch_id' => $branch->id,
            'expense_category_id' => self::taxCategoryId(),
            'description' => $data['description'],
            'amount' => $data['amount'],
            'currency_code' => $country->currency_code,
            'exchange_rate' => $this->rates->rate($country->currency_code, $data['spent_on']),
            'spent_on' => $data['spent_on'],
            'tax_period' => $data['tax_period'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'Bank transfer',
            'status' => 'submitted',
            'submitted_by' => $by->id,
        ]));
    }

    public function markPaid(Expense $expense, User $by): void
    {
        abort_if((int) $expense->submitted_by === (int) $by->id, 403, 'Another person must approve a tax payment you recorded.');
        abort_unless($expense->expense_category_id === self::taxCategoryId(), 422, 'Not a tax payment.');
        abort_unless($expense->status === 'submitted', 422, 'Only submitted tax payments can be approved.');
        $before = $expense->only('status');
        $expense->update(['status' => 'paid', 'approved_by' => $by->id]);
        AuditLogger::log('Approved tax payment', $expense, $before, ['status' => 'paid'], $expense->number);
    }
}
