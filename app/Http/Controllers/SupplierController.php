<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierBankChanged;
use App\Services\AuditLogger;
use App\Services\NumberingService;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $suppliers = Scope::apply(Supplier::with('country'))
            ->when(! $request->user()->isGlobal(), fn ($s) => $s->where('country_id', $request->user()->countryId()))
            ->when($q !== '', fn ($s) => $s->where(fn ($w) => $w->where('company', 'like', "%{$q}%")->orWhere('tax_id', 'like', "%{$q}%")->orWhere('contact_person', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('company')->paginate(50)->withQueryString();

        return view('suppliers.index', ['suppliers' => $suppliers, 'q' => $q]);
    }

    public function create(Request $request)
    {
        $this->authorize('suppliers.manage');

        return view('suppliers.form', ['supplier' => new Supplier(['payment_terms_days' => 30, 'country_id' => Scope::countryId() ?? $request->user()->countryId()]), 'countries' => $this->countries($request)]);
    }

    public function store(Request $request, NumberingService $numbers)
    {
        $this->authorize('suppliers.manage');
        $data = $this->validated($request);
        $country = Country::findOrFail($data['country_id']);
        $this->ensureCountry($country->id);
        $supplier = Supplier::create($data + [
            'code' => sprintf('S-%04d', $numbers->next('SUPPLIER')),
            'currency_code' => $country->currency_code,
        ]);
        AuditLogger::log('Added supplier', $supplier, null, $supplier->only('company', 'tax_id'), $supplier->code);

        return redirect()->route('suppliers.index')->with('status', "{$supplier->company} added as {$supplier->code}. It now appears when you record an expense.");
    }

    public function edit(Request $request, Supplier $supplier)
    {
        $this->authorize('suppliers.manage');
        $this->ensureCountry($supplier->country_id);

        return view('suppliers.form', ['supplier' => $supplier, 'countries' => $this->countries($request)]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('suppliers.manage');
        $this->ensureCountry($supplier->country_id);
        $data = $this->validated($request, $supplier);
        $this->ensureCountry((int) $data['country_id']);
        $bankChanged = ($data['bank_details'] ?? null) !== $supplier->bank_details;
        $supplier->update($data + ['currency_code' => Country::findOrFail($data['country_id'])->currency_code]);

        if ($bankChanged) {
            // The details themselves stay out of the log; only the fact of the change and who made it.
            AuditLogger::log('Changed supplier bank details', $supplier, null, null, $supplier->code);
            $managers = User::role(['Finance Manager', 'Super Administrator'])->where('is_active', true)->where('id', '!=', $request->user()->id)->get();
            Notification::send($managers, new SupplierBankChanged($supplier, $request->user()));
        }

        return redirect()->route('suppliers.index')->with('status', 'Supplier updated.');
    }

    private function countries(Request $request)
    {
        return $request->user()->isGlobal() ? Country::where('is_active', true)->orderBy('name')->get() : collect([$request->user()->country()]);
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'company' => ['required', 'string', 'max:190', Rule::unique('suppliers', 'company')->ignore($supplier?->id)->whereNull('deleted_at')],
            'contact_person' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:190'],
            'country_id' => ['required', 'exists:countries,id'],
            'tax_id' => ['nullable', 'string', 'max:40', Rule::unique('suppliers', 'tax_id')->ignore($supplier?->id)->whereNull('deleted_at')],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'supplies' => ['nullable', 'string', 'max:190'],
            'bank_details' => ['nullable', 'string', 'max:500'],
        ], [
            'company.unique' => 'A supplier with this name already exists.',
            'tax_id.unique' => 'A supplier with this TIN already exists.',
        ]);
    }
}
