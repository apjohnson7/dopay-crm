<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingController;
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
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/s/i/{token}', [SharedDocumentController::class, 'invoice'])->name('shared.invoice')->middleware('throttle:30,1');

// Customer-facing pages and provider notifications (phase 2)
require __DIR__.'/portal.php';
require __DIR__.'/public-payments.php';
require __DIR__.'/public-messaging.php';

Route::middleware('auth')->group(function () {
    Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');
    Route::post('/profile/pin', [ProfileController::class, 'pin'])->name('profile.pin');

    Route::middleware('2fa')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::post('/scope', [ScopeController::class, 'update'])->name('scope');
        Route::get('/search', SearchController::class)->name('search');
        Route::get('/help', HelpController::class)->name('help');

        Route::resource('customers', CustomerController::class)->except('destroy');
        Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
        Route::prefix('accounting')->name('accounting.')->controller(AccountingController::class)->group(function () {
            Route::get('accounts', 'accounts')->name('accounts');
            Route::post('accounts', 'storeAccount')->name('accounts.store');
            Route::get('journals', 'journals')->name('journals');
            Route::post('journals', 'storeJournal')->name('journals.store');
            Route::get('journals/{entry}', 'showJournal')->name('journal');
            Route::get('ledger', 'ledger')->name('ledger');
            Route::get('statements', 'statements')->name('statements');
            Route::get('reconciliation', 'reconciliation')->name('reconciliation');
            Route::post('reconciliation/{action}', 'reconcile')->whereIn('action', ['import', 'auto', 'match', 'unmatch', 'book', 'confirm'])->name('reconcile');
            Route::get('close', 'close')->name('close');
            Route::post('close', 'closePeriod')->name('close.store');
            Route::post('close/reopen', 'reopenPeriod')->name('close.reopen');
        });
        Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account/{country}', [AccountController::class, 'update'])->name('account.update');

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
        Route::post('locale', function (\Illuminate\Http\Request $request) {
            $locale = $request->validate(['locale' => 'required|in:en,fr'])['locale'];
            $request->user()->forceFill(['locale' => $locale])->save();

            return back();
        })->name('locale');

        // Phase 2: getting paid and compliance
        require __DIR__.'/sales.php';
        require __DIR__.'/payments.php';
        require __DIR__.'/einvoicing.php';
        require __DIR__.'/messaging.php';

        Route::post('notifications/read', function () {
            auth()->user()->unreadNotifications->markAsRead();

            return back();
        })->name('notifications.read');
    });
});
