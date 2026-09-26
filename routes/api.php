<?php

use App\Http\Resources\FinanceFormResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\FinanceForm;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Read API for integrations (mobile money, ERP, reporting). Token auth via Sanctum:
|   $user->createToken('erp', ['read'])->plainTextToken
| Write endpoints will follow the same services the web screens use.
*/
Route::middleware(['auth:sanctum', 'abilities:read', 'active', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('/me', fn (Request $r) => $r->user()->only(['id', 'name', 'email']) + ['role' => $r->user()->roleName(), 'country' => $r->user()->country()?->iso2]);
    Route::get('/customers', fn (Request $r) => Customer::query()->when(! $r->user()->isGlobal(), fn ($q) => $q->where('country_id', $r->user()->countryId()))
        ->paginate(50, ['id', 'code', 'type', 'name', 'company', 'phone', 'email', 'city', 'country_id', 'branch_id', 'tax_id', 'category', 'payment_terms_days', 'currency_code', 'created_at']));
    Route::get('/invoices', fn (Request $r) => InvoiceResource::collection(Invoice::visibleTo($r->user())->with('customer')->latest('id')->paginate(50)));
    Route::get('/invoices/{invoice}', function (Request $r, Invoice $invoice) {
        abort_unless($r->user()->canActForCountry($invoice->country_id), 404);

        return new InvoiceResource($invoice->load('items', 'customer'));
    });
    Route::get('/forms', fn (Request $r) => FinanceFormResource::collection(FinanceForm::visibleTo($r->user())->latest('id')->paginate(50)));
});
