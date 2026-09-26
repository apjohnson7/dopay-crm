<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Support\Scope;
use App\Support\SecondApproval;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    public function index(Request $request)
    {
        $filter = $request->query('status', 'All');
        $invoices = Scope::apply(Invoice::query())->with('customer')->latest('issue_date')->latest('id')->get()
            ->filter(fn ($i) => $filter === 'All' || $i->displayStatus() === $filter);

        return view('invoices.index', ['invoices' => $invoices, 'filter' => $filter]);
    }

    public function create(Request $request)
    {
        $this->authorize('invoices.create');

        return view('invoices.form', $this->formData(new Invoice(['issue_date' => today(), 'customer_id' => $request->query('customer')])));
    }

    public function store(Request $request)
    {
        $this->authorize('invoices.create');
        $invoice = $this->service->saveDraft($this->validated($request), $request->user());
        if ($request->boolean('submit')) {
            $this->service->submit($invoice);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', $request->boolean('submit') ? 'Submitted for approval.' : 'Draft saved.');
    }

    public function show(Invoice $invoice)
    {
        $this->ensureCountry($invoice->country_id);
        $invoice->load('customer', 'items', 'allocations.payment.receipt', 'country', 'branch', 'creator', 'approver');

        return view('invoices.show', ['invoice' => $invoice, 'authorizers' => SecondApproval::candidates(auth()->user())]);
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('invoices.create');
        $this->ensureCountry($invoice->country_id);
        abort_unless($invoice->status === 'draft', 403, 'Only drafts can be edited.');

        return view('invoices.form', $this->formData($invoice->load('items')));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $this->authorize('invoices.create');
        $this->ensureCountry($invoice->country_id);
        $this->service->saveDraft($this->validated($request), $request->user(), $invoice);
        if ($request->boolean('submit')) {
            $this->service->submit($invoice->refresh());
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice updated.');
    }

    public function submit(Invoice $invoice)
    {
        $this->authorize('invoices.create');
        $this->service->submit($invoice);

        return back()->with('status', 'Submitted for approval.');
    }

    public function approve(Invoice $invoice, Request $request)
    {
        $this->service->approve($invoice, $request->user());

        return back()->with('status', 'Approved. Generate the official PDF next.');
    }

    public function returnToDraft(Invoice $invoice, Request $request)
    {
        $this->authorize('invoices.approve');
        $this->service->returnToDraft($invoice, $request->validate(['comment' => 'required|string|min:4'])['comment']);

        return back()->with('status', 'Returned to the preparer.');
    }

    public function generate(Invoice $invoice)
    {
        $this->authorize('invoices.create');
        $this->service->generate($invoice);

        return back()->with('status', 'Official invoice generated. Choose how to send it.');
    }

    /** Logs a share (WhatsApp / Telegram / email / link) to the customer's timeline and marks the invoice sent. */
    public function share(Invoice $invoice, Request $request)
    {
        $this->ensureCountry($invoice->country_id);
        abort_unless($invoice->generated_at, 422, 'Generate the official invoice first.');
        $channel = $request->validate(['channel' => 'required|in:WhatsApp,Email,Telegram,IMO,SMS,Link'])['channel'];
        Communication::create(['customer_id' => $invoice->customer_id, 'channel' => $channel, 'kind' => 'Invoice', 'reference' => $invoice->number,
            'recipient' => $channel === 'Email' ? $invoice->customer->email : $invoice->customer->phone, 'status' => 'sent', 'sent_by' => $request->user()->id, 'sent_at' => now()]);
        $this->service->markSent($invoice);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', "Logged: sent via {$channel}.");
    }

    public function cancel(Invoice $invoice, Request $request)
    {
        $this->authorize('invoices.create');
        SecondApproval::verify($request, 'Cancel invoice '.$invoice->number);
        $this->service->cancel($invoice, $request->input('reason'));

        return back()->with('status', 'Invoice cancelled. It stays in the records for the audit trail.');
    }

    public function pdf(Invoice $invoice)
    {
        $this->ensureCountry($invoice->country_id);
        $invoice->load('customer', 'items', 'country', 'branch', 'approver');

        return Pdf::loadView('invoices.pdf', compact('invoice'))->setPaper('a4')->download($invoice->number.'.pdf');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'notes' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|between:0,100',
            'items.*.tax_pct' => 'nullable|numeric|between:0,100',
        ]);
    }

    private function formData(Invoice $invoice): array
    {
        $customers = Scope::apply(Customer::query())->orderBy('company')->orderBy('name')->get()
            ->filter(fn ($c) => auth()->user()->canActForCountry($c->country_id));

        return [
            'invoice' => $invoice,
            'customers' => $customers,
            'products' => Product::where('status', 'active')->with('countries')->orderBy('name')->get(),
            'currencyCodes' => \App\Models\Country::where('is_active', true)->pluck('currency_code')->unique()->values(),
        ];
    }
}
