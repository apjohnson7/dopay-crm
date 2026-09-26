<?php

namespace App\Http\Controllers;

use App\Models\BankStatementLine;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Reconciliation;
use App\Services\Accounting\FinancialStatements;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\ReconciliationService;
use App\Services\ExchangeRateService;
use App\Support\Scope;
use App\Support\SecondApproval;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Phase 1 accounting: chart of accounts, journals, statements, reconciliation and month-end close, per country account. */
class AccountingController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    private function context(Request $request): array
    {
        $this->authorize('reports.view');
        $user = $request->user();
        $countries = $user->isGlobal() ? Country::where('is_active', true)->orderBy('name')->get() : collect([$user->country()])->filter();
        abort_if($countries->isEmpty(), 403);
        $country = $countries->firstWhere('id', (int) $request->query('country', Scope::countryId() ?? $countries->first()->id)) ?? $countries->first();
        $this->ensureCountry($country->id);

        return [$country, $countries];
    }

    public function accounts(Request $request)
    {
        [$country, $countries] = $this->context($request);
        $bal = $this->ledger->balances($country, now($country->timezone));

        return view('accounting.accounts', ['country' => $country, 'countries' => $countries, 'tab' => 'accounts',
            'accounts' => LedgerAccount::where('country_id', $country->id)->orderBy('code')->get()->map(fn ($a) => ['account' => $a, 'balance' => $a->natural($bal[$a->id] ?? 0)])]);
    }

    public function storeAccount(Request $request)
    {
        $this->authorize('journals.approve');
        $country = Country::findOrFail($request->input('country_id'));
        $this->ensureCountry($country->id);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[0-9A-Za-z.]+$/', Rule::unique('ledger_accounts')->where('country_id', $country->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'statement_line' => ['required', 'string', 'max:60'],
        ]);
        $account = LedgerAccount::create($data + ['country_id' => $country->id, 'is_system' => false, 'is_active' => true]);
        \App\Services\AuditLogger::log('Added ledger account', $country, null, $data, $account->label());

        return back()->with('status', "Account {$account->label()} added.");
    }

    public function journals(Request $request)
    {
        [$country, $countries] = $this->context($request);
        $kind = $request->query('kind');
        $entries = JournalEntry::with('lines', 'poster')->where('country_id', $country->id)
            ->when(in_array($kind, ['auto', 'manual', 'opening', 'revaluation'], true), fn ($q) => $q->where('kind', $kind))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', '%'.addcslashes($s, '%_').'%')->orWhere('memo', 'like', '%'.addcslashes($s, '%_').'%')))
            ->latest('entry_date')->latest('id')->paginate(40)->withQueryString();

        return view('accounting.journals', ['country' => $country, 'countries' => $countries, 'tab' => 'journals', 'entries' => $entries, 'kind' => $kind,
            'accounts' => LedgerAccount::where('country_id', $country->id)->where('is_active', true)->orderBy('code')->get(),
            'authorizers' => SecondApproval::candidates($request->user(), $country->id, 'journals.approve'),
            'threshold' => config('accounting.journal_authorization_usd')]);
    }

    public function showJournal(Request $request, JournalEntry $entry)
    {
        $this->authorize('reports.view');
        $this->ensureCountry($entry->country_id);
        $entry->load('lines.account', 'poster', 'country');

        return view('accounting.journal', ['entry' => $entry]);
    }

    public function storeJournal(Request $request, ExchangeRateService $rates)
    {
        $this->authorize('journals.create');
        $country = Country::findOrFail($request->input('country_id'));
        $this->ensureCountry($country->id);
        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:'.now($country->timezone)->addDays(31)->toDateString()],
            'memo' => ['required', 'string', 'max:250'],
            'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.account_id' => ['nullable', 'integer', Rule::exists('ledger_accounts', 'id')->where('country_id', $country->id)->where('is_active', true)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.description' => ['nullable', 'string', 'max:190'],
        ]);
        $lines = collect($data['lines'])->filter(fn ($l) => ! empty($l['account_id']) && ((float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0))
            ->map(fn ($l) => [(int) $l['account_id'], (float) ($l['debit'] ?? 0), (float) ($l['credit'] ?? 0), $l['description'] ?? null])->values()->all();
        if (count($lines) < 2) {
            return back()->withErrors(['lines' => 'A journal needs at least one debit and one credit line.'])->withInput();
        }
        $total = collect($lines)->sum(1);
        $extra = [];
        if ($rates->toBase($total, $country->currency_code) > (float) config('accounting.journal_authorization_usd')) {
            // Large manual journals need a second person's PIN (the preparer can't approve their own).
            $authorizer = SecondApproval::verify($request, 'Manual journal: '.$data['memo'], $country->id, 'journals.approve');
            $extra = ['authorized_by' => $authorizer->id, 'reason' => $request->input('reason')];
        }
        $entry = $this->ledger->post($country, $data['entry_date'], $data['memo'], $lines, 'manual', null, null, $request->user(), $extra);
        if (! $entry) {
            return back()->withErrors(['lines' => 'The amounts round to zero in '.$country->currency_code.'.'])->withInput();
        }

        return redirect()->route('accounting.journal', $entry)->with('status', "Journal {$entry->number} posted.");
    }

    public function ledger(Request $request)
    {
        [$country, $countries] = $this->context($request);
        [$from, $to] = $this->period($request, $country);
        $account = LedgerAccount::where('country_id', $country->id)->find($request->query('account'))
            ?? LedgerAccount::where('country_id', $country->id)->where('role', 'bank')->first();
        $opening = $account ? (float) ($this->ledger->balances($country, Carbon::parse($from)->subDay())[$account->id] ?? 0) : 0;
        $lines = $account ? JournalLine::with('entry')->where('ledger_account_id', $account->id)
            ->whereHas('entry', fn ($e) => $e->whereBetween('entry_date', [$from, $to]))->get()->sortBy(fn ($l) => $l->entry->entry_date->format('Ymd').str_pad((string) $l->id, 10, '0', STR_PAD_LEFT))->values() : collect();

        return view('accounting.ledger', ['country' => $country, 'countries' => $countries, 'tab' => 'ledger', 'account' => $account, 'opening' => $opening, 'lines' => $lines, 'from' => $from, 'to' => $to,
            'accounts' => LedgerAccount::where('country_id', $country->id)->orderBy('code')->get()]);
    }

    public function statements(Request $request, FinancialStatements $fs)
    {
        [$country, $countries] = $this->context($request);
        [$from, $to] = $this->period($request, $country);
        $report = in_array($request->query('report'), ['pl', 'bs', 'tb'], true) ? $request->query('report') : 'pl';

        return view('accounting.statements', ['country' => $country, 'countries' => $countries, 'tab' => 'statements', 'report' => $report, 'from' => $from, 'to' => $to,
            'pl' => $report === 'pl' ? $fs->profitAndLoss($country, $from, $to) : null,
            'bs' => $report === 'bs' ? $fs->balanceSheet($country, $to) : null,
            'tb' => $report === 'tb' ? $fs->trialBalance($country, $to) : null]);
    }

    public function reconciliation(Request $request, ReconciliationService $recon)
    {
        [$country, $countries] = $this->context($request);
        $accounts = LedgerAccount::where('country_id', $country->id)->whereIn('role', array_keys(config('accounting.reconcilable_roles')))->orderBy('code')->get();
        $account = $accounts->firstWhere('id', (int) $request->query('account')) ?? $accounts->first();
        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period')) ? $request->query('period') : now($country->timezone)->format('Y-m');
        [$from, $to] = $recon->range($period);

        return view('accounting.reconciliation', ['country' => $country, 'countries' => $countries, 'tab' => 'reconciliation', 'accounts' => $accounts, 'account' => $account, 'period' => $period,
            'statement' => BankStatementLine::with('journalLine.entry')->where('ledger_account_id', $account?->id)->whereBetween('line_date', [$from, $to])->orderBy('line_date')->get(),
            'book' => $account ? $recon->unmatchedBookLines($account, $period) : collect(),
            'confirmed' => $account ? Reconciliation::with('confirmer')->where('ledger_account_id', $account->id)->where('period', $period)->first() : null,
            'counterAccounts' => LedgerAccount::where('country_id', $country->id)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('role')->orWhereNotIn('role', array_keys(config('accounting.reconcilable_roles'))))->orderBy('code')->get()]);
    }

    public function reconcile(Request $request, ReconciliationService $recon, string $action)
    {
        $this->authorize('journals.create');
        $account = LedgerAccount::findOrFail($request->input('account_id'));
        $this->ensureCountry($account->country_id);
        abort_unless(array_key_exists((string) $account->role, config('accounting.reconcilable_roles')), 422);
        $period = (string) $request->input('period');
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period), 422);
        $user = $request->user();
        $line = fn () => BankStatementLine::where('ledger_account_id', $account->id)->findOrFail($request->input('line_id'));
        $msg = match ($action) {
            'import' => (function () use ($request, $recon, $account, $user) {
                $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']]);

                return $recon->import($account, $request->file('file'), $user).' statement lines imported.';
            })(),
            'auto' => $recon->autoMatch($account, $period, $user).' line(s) matched automatically.',
            'match' => (function () use ($request, $recon, $line, $account, $user) {
                $recon->match($line(), JournalLine::where('ledger_account_id', $account->id)->findOrFail($request->input('journal_line_id')), $user);

                return 'Matched.';
            })(),
            'unmatch' => (function () use ($recon, $line) { $recon->unmatch($line());

                return 'Unmatched.'; })(),
            'book' => (function () use ($request, $recon, $line, $account, $user) {
                $data = $request->validate(['counter_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where('country_id', $account->country_id)->where('is_active', true)], 'memo' => ['required', 'string', 'max:190']]);
                $statementLine = $line();
                // Same control as a manual journal: large amounts need a second person's PIN.
                if (app(ExchangeRateService::class)->toBase(abs((float) $statementLine->amount), $account->country->currency_code) > (float) config('accounting.journal_authorization_usd')) {
                    SecondApproval::verify($request, 'Post statement line: '.$statementLine->description, $account->country_id, 'journals.approve');
                }
                $recon->bookAndMatch($statementLine, LedgerAccount::findOrFail($data['counter_account_id']), $data['memo'], $user);

                return 'Entry posted and matched.';
            })(),
            'confirm' => (function () use ($recon, $account, $period, $user) { $recon->confirm($account, $period, $user);

                return 'Reconciliation confirmed for '.$period.'.'; })(),
            default => abort(404),
        };

        return redirect()->route('accounting.reconciliation', ['country' => $account->country_id, 'account' => $account->id, 'period' => $period])->with('status', $msg);
    }

    public function close(Request $request, PeriodCloseService $close)
    {
        [$country, $countries] = $this->context($request);
        $next = $close->nextToClose($country);

        return view('accounting.close', ['country' => $country, 'countries' => $countries, 'tab' => 'close', 'next' => $next, 'checks' => $close->checklist($country, $next),
            'authorizers' => SecondApproval::candidates($request->user(), $country->id, 'books.close')]);
    }

    public function closePeriod(Request $request, PeriodCloseService $close)
    {
        $this->authorize('books.close');
        $country = Country::findOrFail($request->input('country_id'));
        $this->ensureCountry($country->id);
        $close->close($country, (string) $request->input('period'), $request->user());

        return back()->with('status', Carbon::createFromFormat('!Y-m', (string) $request->input('period'))->format('F Y').' is closed and locked.');
    }

    public function reopenPeriod(Request $request, PeriodCloseService $close)
    {
        $this->authorize('books.close');
        $country = Country::findOrFail($request->input('country_id'));
        $this->ensureCountry($country->id);
        $authorizer = SecondApproval::verify($request, 'Reopen '.$country->books_closed_through.' for '.$country->name, $country->id, 'books.close');
        $close->reopen($country, $authorizer, (string) $request->input('reason'));

        return back()->with('status', 'The last closed month has been reopened.');
    }

    private function period(Request $request, Country $country): array
    {
        $today = now($country->timezone);
        $to = $request->date('to') ?? $today;
        $from = $request->date('from') ?? $today->copy()->startOfYear();

        return [$from->toDateString(), $to->toDateString()];
    }
}
