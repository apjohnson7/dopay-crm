<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Scope;
use App\Support\SecondApproval;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Scope::apply(Payment::query())->with('customer', 'receipt', 'allocations.invoice', 'receiver')->latest('paid_on')->latest('id')->paginate(30);

        return view('payments.index', ['payments' => $payments, 'authorizers' => SecondApproval::candidates(auth()->user(), auth()->user()->isGlobal() ? \App\Support\Scope::countryId() : auth()->user()->countryId(), 'payments.reverse')]);
    }

    public function create(Request $request)
    {
        $this->authorize('payments.record');
        $customers = Scope::apply(Customer::query())->orderBy('company')->orderBy('name')->get()->filter(fn ($c) => auth()->user()->canActForCountry($c->country_id));
        $customer = $request->query('customer') ? $customers->firstWhere('id', (int) $request->query('customer')) : null;
        $open = $customer ? Invoice::where('customer_id', $customer->id)->whereIn('status', ['approved', 'sent'])->orderBy('due_date')->get()->filter(fn ($i) => $i->balance() > 0) : collect();

        return view('payments.create', ['customers' => $customers, 'customer' => $customer, 'open' => $open, 'preselect' => $request->query('invoice')]);
    }

    public function store(Request $request, PaymentService $service)
    {
        $this->authorize('payments.record');
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:'.implode(',', config('dopay.payment_methods')),
            'reference' => 'nullable|string|max:120',
            'paid_on' => 'required|date|before_or_equal:today',
            'allocations' => 'nullable|array',
            'allocations.*' => 'nullable|numeric|min:0',
        ]);
        $customer = Customer::findOrFail($data['customer_id']);
        $this->ensureCountry($customer->country_id);
        $payment = $service->record($customer, $data, $data['allocations'] ?? [], $request->user());

        return redirect()->route('receipts.show', $payment->receipt)->with('status', 'Payment recorded and receipt issued.');
    }

    public function reverse(Payment $payment, Request $request, PaymentService $service)
    {
        $this->authorize('payments.reverse');
        $this->ensureCountry($payment->country_id);
        SecondApproval::verify($request, 'Reverse payment '.$payment->number, $payment->country_id, 'payments.reverse');
        $service->reverse($payment, $request->user(), $request->input('reason'));

        return back()->with('status', 'Payment reversed and receipt voided.');
    }
}
