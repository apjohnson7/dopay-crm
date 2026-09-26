<?php

use App\Http\Resources\FinanceFormResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\FinanceForm;
use App\Models\Invoice;
use App\Support\Scope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Read API for integrations (mobile money, ERP, reporting). Token auth via Sanctum:
|   $user->createToken('erp', ['read'])->plainTextToken
| Write endpoints will follow the same services the web screens use.
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('/me', fn (Request $r) => $r->user()->only(['id', 'name', 'email']) + ['role' => $r->user()->roleName(), 'country' => $r->user()->country()?->iso2]);
    Route::get('/customers', fn () => Scope::apply(Customer::query())->paginate(50));
    Route::get('/invoices', fn (Request $r) => InvoiceResource::collection(Invoice::visibleTo($r->user())->with('customer')->latest('id')->paginate(50)));
    Route::get('/invoices/{invoice}', fn (Invoice $invoice) => new InvoiceResource($invoice->load('items', 'customer')));
    Route::get('/forms', fn (Request $r) => FinanceFormResource::collection(FinanceForm::visibleTo($r->user())->latest('id')->paginate(50)));
});
