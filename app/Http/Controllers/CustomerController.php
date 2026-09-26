<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Services\NumberingService;
use App\Support\Scope;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $customers = Scope::apply(Customer::query())->with('branch')
            ->when($q, fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('company', 'like', "%$q%")->orWhere('code', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('tax_id', 'like', "%$q%")))
            ->orderBy('company')->orderBy('name')->paginate(25)->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        $this->authorize('customers.manage');

        return view('customers.form', ['customer' => new Customer(['payment_terms_days' => 30, 'type' => 'business']), 'branches' => $this->branches()]);
    }

    public function store(Request $request, NumberingService $numbers)
    {
        $this->authorize('customers.manage');
        $data = $this->validated($request);
        $branch = Branch::with('country')->findOrFail($data['branch_id']);
        $this->ensureCountry($branch->country_id);
        $seq = $numbers->next('CUST:'.$branch->country->iso2);
        $customer = Customer::create($data + [
            'code' => sprintf('C-%s-%04d', $branch->country->iso2, $seq),
            'country_id' => $branch->country_id,
            'currency_code' => $branch->country->currency_code,
        ]);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer saved.');
    }

    public function show(Customer $customer, Request $request)
    {
        $this->ensureCountry($customer->country_id);
        $customer->load(['invoices' => fn ($q) => $q->latest('issue_date'), 'payments.receipt', 'communications' => fn ($q) => $q->latest()->limit(30), 'branch', 'country', 'accountManager']);
        [$from, $to] = $this->period($request->query('period', 'year'));

        return view('customers.show', ['customer' => $customer, 'fin' => $customer->financials(), 'tab' => $request->query('tab', 'timeline'),
            'statement' => $this->statement($customer, $from, $to), 'period' => $request->query('period', 'year')]);
    }

    public function edit(Customer $customer)
    {
        $this->authorize('customers.manage');
        $this->ensureCountry($customer->country_id);

        return view('customers.form', ['customer' => $customer, 'branches' => $this->branches()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('customers.manage');
        $this->ensureCountry($customer->country_id);
        $customer->update($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => 'required|in:business,member',
            'name' => 'required|string|max:120',
            'company' => 'nullable|string|max:160',
            'phone' => 'required_without:email|nullable|string|max:40',
            'email' => 'required_without:phone|nullable|email|max:160',
            'address' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:80',
            'branch_id' => 'required|exists:branches,id',
            'tax_id' => 'nullable|string|max:40',
            'registration_no' => 'nullable|string|max:60',
            'industry' => 'nullable|string|max:80',
            'category' => 'nullable|string|max:40',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms_days' => 'required|integer|min:0|max:365',
            'gender' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'id_type' => 'nullable|string|max:40',
            'id_number' => 'nullable|string|max:60',
        ]) + ['account_manager_id' => $request->user()->id];
    }

    private function branches()
    {
        return Scope::apply(Branch::with('country'), 'country_id')->where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($b) => auth()->user()->canActForCountry($b->country_id));
    }

    private function period(string $p): array
    {
        return match ($p) {
            'month' => [now()->startOfMonth(), now()],
            'last' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [now()->firstOfQuarter(), now()],
            'all' => [now()->subYears(20), now()],
            default => [now()->startOfYear(), now()],
        };
    }

    private function statement(Customer $customer, $from, $to): array
    {
        $tx = collect();
        foreach ($customer->invoices->whereIn('status', ['approved', 'sent']) as $i) {
            $tx->push(['date' => $i->issue_date, 'ref' => $i->number, 'desc' => 'Invoice', 'debit' => (float) $i->total, 'credit' => 0]);
        }
        foreach ($customer->payments->where('status', 'completed') as $p) {
            $tx->push(['date' => $p->paid_on, 'ref' => $p->number, 'desc' => 'Payment · '.$p->method, 'debit' => 0, 'credit' => (float) $p->amount]);
        }
        $tx = $tx->sortBy('date')->values();
        $opening = $tx->filter(fn ($t) => $t['date']->lt($from))->sum(fn ($t) => $t['debit'] - $t['credit']);
        $bal = $opening;
        $rows = $tx->filter(fn ($t) => $t['date']->betweenIncluded($from, $to))->map(function ($t) use (&$bal) {
            $bal += $t['debit'] - $t['credit'];

            return $t + ['balance' => $bal];
        })->values();

        return ['from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows, 'closing' => $bal];
    }
}
