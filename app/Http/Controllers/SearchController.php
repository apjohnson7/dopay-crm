<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinanceForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Scope;
use Illuminate\Http\Request;

/** "John 250000" finds records where every word matches something. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $words = array_filter(preg_split('/\s+/', str_replace(',', '', $q)));
        $results = [];
        if ($words) {
            $like = fn ($query, array $cols) => collect($words)->each(fn ($w) => $query->where(fn ($x) => collect($cols)->each(fn ($c) => $x->orWhere($c, 'like', "%{$w}%"))));

            $results['Customers'] = tap(Scope::apply(Customer::query()), fn ($b) => $like($b, ['code', 'name', 'company', 'phone', 'email', 'tax_id']))->limit(8)->get()
                ->map(fn ($c) => ['title' => $c->displayName(), 'sub' => $c->code.' · '.$c->phone, 'url' => route('customers.show', $c)]);
            $results['Invoices'] = tap(Scope::apply(Invoice::query(), 'invoices.country_id')->join('customers', 'customers.id', '=', 'invoices.customer_id')->select('invoices.*'),
                fn ($b) => $like($b, ['invoices.number', 'invoices.total', 'customers.name', 'customers.company']))->limit(8)->get()
                ->map(fn ($i) => ['title' => $i->number, 'sub' => money($i->total, $i->currency_code).' · '.$i->displayStatus(), 'url' => route('invoices.show', $i)]);
            $results['Payments & receipts'] = tap(Scope::apply(Payment::query(), 'payments.country_id')->join('customers', 'customers.id', '=', 'payments.customer_id')->select('payments.*'),
                fn ($b) => $like($b, ['payments.number', 'payments.reference', 'payments.amount', 'customers.name', 'customers.company']))->with('receipt')->limit(8)->get()
                ->map(fn ($p) => ['title' => $p->number, 'sub' => money($p->amount, $p->currency_code).' · '.$p->method.' · '.$p->reference, 'url' => $p->receipt ? route('receipts.show', $p->receipt) : route('payments.index')]);
            $results['Finance forms'] = tap(FinanceForm::visibleTo($request->user()), fn ($b) => $like($b, ['reference', 'data', 'total']))->limit(8)->get()
                ->map(fn ($f) => ['title' => $f->reference, 'sub' => $f->name().' · '.$f->statusLabel(), 'url' => route('forms.show', $f)]);
        }

        return view('search.index', ['q' => $q, 'results' => array_filter($results, fn ($r) => count($r))]);
    }
}
