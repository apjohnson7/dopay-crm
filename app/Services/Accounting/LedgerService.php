<?php

namespace App\Services\Accounting;

use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NumberingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way anything reaches the books. Every entry must balance, may not fall in a closed month,
 * and automatic postings are idempotent through their source key (posting the same invoice twice does nothing).
 */
class LedgerService
{
    public function __construct(private ChartOfAccounts $chart, private NumberingService $numbers) {}

    public function isLocked(Country $country, $date): bool
    {
        return $country->books_closed_through && Carbon::parse($date)->format('Y-m') <= $country->books_closed_through;
    }

    public function assertOpen(Country $country, $date, string $field = 'date'): void
    {
        if ($this->isLocked($country, $date)) {
            $d = Carbon::parse($date);
            throw ValidationException::withMessages([$field => "{$d->format('F Y')} is closed for {$country->name}. Use a date in an open month, or ask a Finance Manager to reopen the period."]);
        }
    }

    /**
     * @param  array<int, array{0: string|int, 1: float|int, 2: float|int, 3?: string|null}>  $lines  [role or account id, debit, credit, description]
     */
    public function post(Country $country, $date, string $memo, array $lines, string $kind = 'auto', ?string $sourceKey = null, ?Model $source = null, ?User $by = null, array $extra = []): ?JournalEntry
    {
        if ($sourceKey && ($existing = JournalEntry::where('source_key', $sourceKey)->first())) {
            return $existing;
        }
        $decimals = \App\Models\Currency::decimalsFor($country->currency_code);
        $rows = collect($lines)->map(function ($l) use ($country, $decimals) {
            $account = is_int($l[0]) ? LedgerAccount::where('country_id', $country->id)->findOrFail($l[0]) : $this->chart->account($country->id, $l[0]);

            return ['account' => $account, 'debit' => round((float) $l[1], $decimals), 'credit' => round((float) $l[2], $decimals), 'description' => $l[3] ?? null];
        });
        if ($rows->contains(fn ($r) => $r['debit'] < 0 || $r['credit'] < 0)) {
            throw ValidationException::withMessages(['lines' => 'Debits and credits must be positive.']);
        }
        $rows = $rows->filter(fn ($r) => $r['debit'] > 0 || $r['credit'] > 0)->values();
        if ($rows->isEmpty()) {
            return null;
        }
        // Rounding from currency conversion lands on the largest line of the short side.
        $gap = round($rows->sum('debit') - $rows->sum('credit'), $decimals);
        if (abs($gap) > 0 && abs($gap) <= 1 && $kind === 'auto') {
            $side = $gap > 0 ? 'credit' : 'debit';
            $i = $rows->filter(fn ($r) => $r[$side] > 0)->sortByDesc($side)->keys()->first();
            $row = $rows[$i];
            $row[$side] = round($row[$side] + abs($gap), $decimals);
            $rows[$i] = $row;
            $gap = 0;
        }
        if (abs($gap) > 0) {
            throw ValidationException::withMessages(['lines' => 'Debits ('.number_format($rows->sum('debit'), $decimals).') must equal credits ('.number_format($rows->sum('credit'), $decimals).').']);
        }
        $this->assertOpen($country, $date);

        return DB::transaction(function () use ($country, $date, $memo, $rows, $kind, $sourceKey, $source, $by, $extra) {
            $entry = JournalEntry::create([
                'country_id' => $country->id, 'number' => $this->numbers->document($country, 'JNL', (int) Carbon::parse($date)->format('Y')),
                'entry_date' => Carbon::parse($date)->toDateString(), 'memo' => mb_substr($memo, 0, 250), 'kind' => $kind, 'source_key' => $sourceKey,
                'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(), 'posted_by' => $by?->id ?? auth()->id(),
            ] + $extra);
            foreach ($rows as $r) {
                $entry->lines()->create(['ledger_account_id' => $r['account']->id, 'debit' => $r['debit'], 'credit' => $r['credit'], 'description' => $r['description']]);
            }
            if ($kind !== 'auto') {
                AuditLogger::log('Posted journal', $entry, null, ['memo' => $entry->memo, 'total' => $rows->sum('debit')], $entry->number);
            }

            return $entry;
        });
    }

    /** Posts the mirror image of an automatic entry (used when a payment is reversed or an invoice cancelled). */
    public function reverse(string $sourceKey, $date, string $memo): ?JournalEntry
    {
        $original = JournalEntry::with('lines', 'country')->where('source_key', $sourceKey)->first();
        if (! $original) {
            return null;
        }
        $country = $original->country;
        if ($this->isLocked($country, $date)) {
            $date = now($country->timezone)->toDateString(); // the reversal lands in the current open month
        }

        return $this->post($country, $date, $memo, $original->lines->map(fn ($l) => [(int) $l->ledger_account_id, (float) $l->credit, (float) $l->debit, $l->description])->all(),
            'auto', $sourceKey.':reversal', $original->source);
    }

    /** Debit-minus-credit per account id, for entries on or before $to (and on or after $from when given). */
    public function balances(Country $country, $to, $from = null): Collection
    {
        return DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.country_id', $country->id)
            ->where('journal_entries.entry_date', '<=', Carbon::parse($to)->toDateString())
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', Carbon::parse($from)->toDateString()))
            ->groupBy('journal_lines.ledger_account_id')
            ->selectRaw('journal_lines.ledger_account_id as id, SUM(journal_lines.debit) - SUM(journal_lines.credit) as bal')
            ->pluck('bal', 'id')->map(fn ($v) => (float) $v);
    }
}
