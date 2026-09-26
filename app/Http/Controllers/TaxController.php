<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Expense;
use App\Services\AuditLogger;
use App\Services\TaxService;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    public function index(Request $request, TaxService $tax)
    {
        $this->authorize('reports.view');
        $user = $request->user();
        $countries = $user->isGlobal() ? Country::where('is_active', true)->orderBy('name')->get() : collect([$user->country()]);
        $country = $countries->firstWhere('id', (int) $request->query('country', Scope::countryId() ?? $countries->first()->id)) ?? $countries->first();
        $this->ensureCountry($country->id);

        $rows = $tax->periods($country);
        $year = now($country->timezone)->format('Y');
        $selected = $rows->firstWhere('period', $request->query('period'))
            ?? $rows->first(fn ($r) => $r['status'] !== 'Open period') ?? $rows->first();
        $payments = $tax->taxPayments($country);

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($rows, $country) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Country', 'Period', 'Taxable sales ('.$country->currency_code.')', $country->tax_name.' charged', 'Remitted', 'Balance', 'Due by', 'Status']);
                foreach ($rows as $r) {
                    fputcsv($out, array_map([self::class, 'csvSafe'], [$country->name, $r['label'], $r['net'], $r['vat'], $r['paid'], $r['balance'], $r['due']->toDateString(), $r['status']]));
                }
                fclose($out);
            }, 'dopay-'.strtolower($country->tax_name).'-'.$country->iso2.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('taxes.index', [
            'country' => $country, 'countries' => $countries, 'rows' => $rows, 'selected' => $selected, 'year' => $year,
            'yearRows' => $rows->filter(fn ($r) => str_starts_with($r['period'], $year)),
            'owed' => $rows->filter(fn ($r) => $r['status'] !== 'Open period' && $r['balance'] > 0)->sortBy(fn ($r) => $r['due']->timestamp),
            'open' => $rows->firstWhere('status', 'Open period'),
            'other' => $payments->reject(fn ($e) => TaxService::isVat($e)),
            'pending' => $payments->where('status', '!=', 'paid'),
            'branches' => Branch::where('country_id', $country->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Stop spreadsheet formula injection: text starting with = + - @ (or tab/CR) is prefixed with an apostrophe. */
    public static function csvSafe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v;
    }

    public function store(Request $request, TaxService $tax)
    {
        $this->authorize('expenses.create');
        $data = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'tax_period' => ['nullable', 'date_format:Y-m'],
            'description' => ['required', 'string', 'max:190'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(config('dopay.payment_methods'))],
        ]);
        $country = Country::findOrFail($data['country_id']);
        $this->ensureCountry($country->id);
        $expense = $tax->record($country, Branch::findOrFail($data['branch_id']), $data, $request->user());
        AuditLogger::log('Recorded tax payment', $expense, null, $expense->only('amount', 'tax_period'), $expense->number);

        return redirect()->route('taxes.index', ['country' => $country->id])
            ->with('status', "Tax payment {$expense->number} recorded. It counts as remitted once an approver marks it paid.");
    }

    public function approve(Request $request, Expense $expense, TaxService $tax)
    {
        $this->authorize('expenses.approve');
        $this->ensureCountry($expense->country_id);
        $tax->markPaid($expense, $request->user());

        return back()->with('status', "Tax payment {$expense->number} marked paid.");
    }

    public function settings(Request $request)
    {
        $this->authorize('settings.manage');
        $data = $request->validate([
            'countries' => ['required', 'array'],
            'countries.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'countries.*.vat_filing_day' => ['required', 'integer', 'min:1', 'max:28'],
        ]);
        foreach ($data['countries'] as $id => $v) {
            $c = Country::findOrFail($id);
            $this->ensureCountry($c->id);
            $before = $c->only('tax_rate', 'vat_filing_day');
            $c->update($v);
            if ($before != $c->only('tax_rate', 'vat_filing_day')) {
                AuditLogger::log('Changed tax settings', $c, $before, $c->only('tax_rate', 'vat_filing_day'), $c->name);
            }
        }

        return back()->with('status', 'Tax rates and filing days saved. Issued invoices keep the rate they were created with.');
    }
}
