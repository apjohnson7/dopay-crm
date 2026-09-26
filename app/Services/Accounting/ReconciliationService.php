<?php

namespace App\Services\Accounting;

use App\Models\BankStatementLine;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Matches bank and mobile money statement lines to the ledger. A statement line matches at most one ledger line;
 * whatever stays unmatched is a missing entry, a timing difference or an error.
 */
class ReconciliationService
{
    public function __construct(private LedgerService $ledger) {}

    /** Imports a CSV with columns date, description, amount (money out negative) and optional reference. */
    public function import(LedgerAccount $account, UploadedFile $file, User $by): int
    {
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim((string) file_get_contents($file->getRealPath()))));
        $header = array_map(fn ($h) => Str::lower(trim((string) $h, " \t\"\u{FEFF}")), array_shift($rows) ?? []);
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $header, true))->first(fn ($i) => $i !== false);
        [$d, $desc, $amt, $ref] = [$col(['date', 'value date', 'transaction date']), $col(['description', 'details', 'narration']), $col(['amount', 'value']), $col(['reference', 'ref'])];
        if ($d === null || $desc === null || $amt === null) {
            throw ValidationException::withMessages(['file' => 'The CSV needs date, description and amount columns (money out as negative amounts).']);
        }
        $batch = Str::upper(Str::random(8));
        $count = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $d, $desc, $amt, $ref, $account, $by, $batch, &$count, &$skipped) {
            foreach ($rows as $i => $r) {
                if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0 || ! isset($r[$d], $r[$desc], $r[$amt])) {
                    continue;
                }
                $amount = self::parseAmount((string) $r[$amt]);
                if ($amount === null || abs($amount) < 0.005) {
                    throw ValidationException::withMessages(['file' => 'Row '.($i + 2).' has an amount that could not be read: “'.$r[$amt].'”.']);
                }
                $date = self::parseDate((string) $r[$d]) ?? throw ValidationException::withMessages(['file' => 'Row '.($i + 2).' has a date that could not be read (use YYYY-MM-DD or DD/MM/YYYY).']);
                $reference = $ref !== null ? mb_substr(trim((string) ($r[$ref] ?? '')), 0, 120) : null;
                // The same line imported twice (same date, amount and reference or description) is skipped.
                if (BankStatementLine::where('ledger_account_id', $account->id)->whereDate('line_date', $date)->where('amount', $amount)
                    ->where(fn ($q) => $reference ? $q->where('reference', $reference) : $q->where('description', mb_substr(trim((string) $r[$desc]), 0, 250)))->exists()) {
                    $skipped++;

                    continue;
                }
                if (Reconciliation::where('ledger_account_id', $account->id)->where('period', substr($date, 0, 7))->exists()) {
                    throw ValidationException::withMessages(['file' => 'Row '.($i + 2).' falls in '.substr($date, 0, 7).', which is already confirmed as reconciled.']);
                }
                BankStatementLine::create(['country_id' => $account->country_id, 'ledger_account_id' => $account->id, 'line_date' => $date,
                    'description' => mb_substr(trim((string) $r[$desc]), 0, 250) ?: 'Statement line', 'reference' => $reference,
                    'amount' => $amount, 'import_batch' => $batch, 'imported_by' => $by->id]);
                $count++;
            }
        });
        AuditLogger::log('Imported statement', $account, null, ['lines' => $count, 'duplicates_skipped' => $skipped, 'batch' => $batch], $account->label());

        return $count;
    }

    /** "1,250,000", "-47,500", "(1,000.00)", "UGX 50,000" and "1 250 000,50" style amounts. */
    public static function parseAmount(string $raw): ?float
    {
        $v = trim($raw);
        $negative = str_starts_with($v, '(') && str_ends_with($v, ')') || str_starts_with($v, '-') || str_ends_with($v, '-') || str_contains(strtoupper($v), 'DR');
        $v = preg_replace('/[^\d.,]/', '', $v);
        if ($v === '' || $v === null) {
            return null;
        }
        if (preg_match('/,\d{1,2}$/', $v) && ! str_contains($v, '.')) {
            $v = str_replace(',', '.', str_replace('.', '', $v)); // decimal comma
        } else {
            $v = str_replace(',', '', $v);
        }
        if (! is_numeric($v)) {
            return null;
        }

        return $negative ? -abs((float) $v) : (float) $v;
    }

    /** ISO dates, or day-first dates as East and West African banks print them (26/09/2026, 26-09-2026, 26 Sep 2026). */
    public static function parseDate(string $raw): ?string
    {
        $v = trim($raw);
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!d/m/y', '!j M Y', '!d M Y', '!d-M-Y', '!d-M-y'] as $f) {
            $dt = \DateTime::createFromFormat($f, $v);
            if ($dt && $dt->format(ltrim($f, '!')) === $v) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /** Ledger lines on this account in the month that no statement line has claimed yet. */
    public function unmatchedBookLines(LedgerAccount $account, string $period): Collection
    {
        [$from, $to] = $this->range($period);

        return JournalLine::with('entry')->where('ledger_account_id', $account->id)
            ->whereHas('entry', fn ($e) => $e->whereBetween('entry_date', [$from, $to]))
            ->whereNotIn('id', BankStatementLine::whereNotNull('journal_line_id')->select('journal_line_id'))
            ->get()->sortBy(fn ($l) => $l->entry->entry_date)->values();
    }

    /** Same amount, dates within 5 days, and (when both have one) the same reference wins over a closer date. */
    public function autoMatch(LedgerAccount $account, string $period, User $by): int
    {
        $this->assertPeriodOpen($account, $period);
        [$from, $to] = $this->range($period);
        $book = $this->unmatchedBookLines($account, $period);
        $matched = 0;
        BankStatementLine::where('ledger_account_id', $account->id)->whereNull('journal_line_id')->whereBetween('line_date', [$from, $to])->orderBy('line_date')->get()
            ->each(function (BankStatementLine $s) use (&$book, $by, &$matched) {
                $candidates = $book->filter(fn (JournalLine $l) => abs(((float) $l->debit - (float) $l->credit) - (float) $s->amount) < 0.005
                    && abs($l->entry->entry_date->diffInDays($s->line_date)) <= 5);
                if ($candidates->isEmpty()) {
                    return;
                }
                $best = $candidates->sortBy(fn (JournalLine $l) => ($s->reference && str_contains((string) $l->entry->memo.' '.$l->description, $s->reference) ? 0 : 100)
                    + abs($l->entry->entry_date->diffInDays($s->line_date)))->first();
                $s->update(['journal_line_id' => $best->id, 'matched_by' => $by->id, 'matched_at' => now()]);
                $book = $book->reject(fn ($l) => $l->id === $best->id)->values();
                $matched++;
            });

        return $matched;
    }

    public function match(BankStatementLine $line, JournalLine $book, User $by): void
    {
        $this->assertNotConfirmed($line);
        abort_if($line->journal_line_id, 422, 'That statement line is already matched. Unmatch it first.');
        abort_unless((int) $book->ledger_account_id === (int) $line->ledger_account_id, 422, 'That entry is on a different account.');
        abort_if(BankStatementLine::where('journal_line_id', $book->id)->exists(), 422, 'That entry is already matched.');
        if (abs(((float) $book->debit - (float) $book->credit) - (float) $line->amount) >= 0.005) {
            throw ValidationException::withMessages(['match' => 'The amounts differ. Post a correcting entry for the difference first.']);
        }
        $line->update(['journal_line_id' => $book->id, 'matched_by' => $by->id, 'matched_at' => now()]);
    }

    public function unmatch(BankStatementLine $line): void
    {
        $this->assertNotConfirmed($line);
        $line->update(['journal_line_id' => null, 'matched_by' => null, 'matched_at' => null]);
    }

    /**
     * Books a statement-only line (bank charges, interest, a deposit to identify later) against a chosen account,
     * then matches it.
     */
    public function bookAndMatch(BankStatementLine $line, LedgerAccount $counter, string $memo, User $by): void
    {
        $this->assertNotConfirmed($line);
        abort_if($line->journal_line_id, 422, 'That statement line is already matched.');
        abort_unless((int) $counter->country_id === (int) $line->country_id, 422);
        DB::transaction(function () use ($line, $counter, $memo, $by) {
            $amount = abs((float) $line->amount);
            $bank = (int) $line->ledger_account_id;
            $lines = $line->amount >= 0 ? [[$bank, $amount, 0, $line->reference], [(int) $counter->id, 0, $amount]] : [[(int) $counter->id, $amount, 0], [$bank, 0, $amount, $line->reference]];
            $entry = $this->ledger->post($line->account->country, $line->line_date, $memo.' ('.$line->description.')', $lines, 'manual', 'statement:'.$line->id.':'.now()->format('YmdHisv'), $line, $by);
            abort_unless($entry, 422, 'The amount rounds to zero.');
            $bookLine = $entry->lines()->where('ledger_account_id', $bank)->firstOrFail();
            $line->update(['journal_line_id' => $bookLine->id, 'matched_by' => $by->id, 'matched_at' => now()]);
        });
    }

    /** Confirms the month once every statement line is matched. */
    public function confirm(LedgerAccount $account, string $period, User $by): void
    {
        [$from, $to] = $this->range($period);
        $lines = BankStatementLine::where('ledger_account_id', $account->id)->whereBetween('line_date', [$from, $to]);
        if (! (clone $lines)->exists()) {
            throw ValidationException::withMessages(['period' => 'Import the statement for the month first.']);
        }
        $open = (clone $lines)->whereNull('journal_line_id')->count();
        if ($open) {
            throw ValidationException::withMessages(['period' => "{$open} statement line(s) are still unmatched."]);
        }
        Reconciliation::updateOrCreate(['ledger_account_id' => $account->id, 'period' => $period], ['confirmed_by' => $by->id, 'confirmed_at' => now()]);
        AuditLogger::log('Confirmed reconciliation', $account, null, ['period' => $period], $account->label());
    }

    public function range(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01');

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    private function assertPeriodOpen(LedgerAccount $account, string $period): void
    {
        if (Reconciliation::where('ledger_account_id', $account->id)->where('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => 'This month is already confirmed as reconciled.']);
        }
    }

    private function assertNotConfirmed(BankStatementLine $line): void
    {
        $period = $line->line_date->format('Y-m');
        if (Reconciliation::where('ledger_account_id', $line->ledger_account_id)->where('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => 'This month is already confirmed as reconciled.']);
        }
    }
}
