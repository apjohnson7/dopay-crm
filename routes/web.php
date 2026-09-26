<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BudgetMonitorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceFormController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ScopeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SharedDocumentController;
use App\Http\Controllers\TaxController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/s/i/{token}', [SharedDocumentController::class, 'invoice'])->name('shared.invoice')->middleware('throttle:30,1');

Route::middleware('auth')->group(function () {
    Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');
    Route::post('/profile/pin', [ProfileController::class, 'pin'])->name('profile.pin');

    Route::middleware('2fa')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::post('/scope', [ScopeController::class, 'update'])->name('scope');
        Route::get('/search', SearchController::class)->name('search');
        Route::get('/help', HelpController::class)->name('help');

        Route::resource('customers', CustomerController::class)->except('destroy');

        Route::resource('invoices', InvoiceController::class)->except('destroy');
        Route::post('invoices/{invoice}/submit', [InvoiceController::class, 'submit'])->name('invoices.submit');
        Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
        Route::post('invoices/{invoice}/return', [InvoiceController::class, 'returnToDraft'])->name('invoices.return');
        Route::post('invoices/{invoice}/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::post('invoices/{invoice}/share', [InvoiceController::class, 'share'])->name('invoices.share');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');

        Route::get('forms', [FinanceFormController::class, 'index'])->name('forms.index');
        Route::get('forms/new/{type}', [FinanceFormController::class, 'create'])->whereIn('type', ['A', 'B', 'C', 'F', 'G', 'J', 'K'])->name('forms.create');
        Route::post('forms/new/{type}', [FinanceFormController::class, 'store'])->whereIn('type', ['A', 'B', 'C', 'F', 'G', 'J', 'K'])->name('forms.store');
        Route::get('forms/{form}', [FinanceFormController::class, 'show'])->name('forms.show');
        Route::get('forms/{form}/edit', [FinanceFormController::class, 'edit'])->name('forms.edit');
        Route::put('forms/{form}', [FinanceFormController::class, 'update'])->name('forms.update');
        Route::post('forms/{form}/submit', [FinanceFormController::class, 'submit'])->name('forms.submit');
        Route::post('forms/{form}/sign', [FinanceFormController::class, 'sign'])->name('forms.sign');
        Route::post('forms/{form}/return', [FinanceFormController::class, 'returnToPreparer'])->name('forms.return');
        Route::post('forms/{form}/revise', [FinanceFormController::class, 'revise'])->name('forms.revise');
        Route::post('forms/{form}/void', [FinanceFormController::class, 'void'])->name('forms.void');
        Route::post('forms/{form}/action/{action}', [FinanceFormController::class, 'action'])->whereIn('action', ['paid', 'reimburse', 'disburse', 'liquidate', 'settle'])->name('forms.action');
        Route::get('forms/{form}/pdf', [FinanceFormController::class, 'pdf'])->name('forms.pdf');
        Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
        Route::post('taxes/payments', [TaxController::class, 'store'])->name('taxes.store');
        Route::post('taxes/payments/{expense}/approve', [TaxController::class, 'approve'])->name('taxes.approve');
        Route::post('taxes/settings', [TaxController::class, 'settings'])->name('taxes.settings');
        Route::get('budget-monitoring', BudgetMonitorController::class)->name('budget.index');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('messages/{conversation}', [MessageController::class, 'send'])->name('messages.send');

        Route::get('audit', AuditLogController::class)->name('audit.index');

        Route::post('notifications/read', function () {
            auth()->user()->unreadNotifications->markAsRead();

            return back();
        })->name('notifications.read');
    });
});
