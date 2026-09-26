<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Document;
use App\Models\Expense;
use App\Models\FinanceForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ApprovalService;
use App\Services\ExchangeRateService;
use App\Services\MessagingService;
use App\Services\TaxService;
use App\Support\Scope;

class DashboardController extends Controller
{
    public function __invoke(ApprovalService $approvals, MessagingService $messaging, ExchangeRateService $rates, TaxService $tax)
    {
        $user = auth()->user();
        $countryId = Scope::countryId();
        $currency = $countryId ? Country::find($countryId)->currency_code : config('dopay.base_currency');
        $conv = fn ($amount, $cur, $on = null) => $countryId ? (float) $amount : $rates->toBase((float) $amount, $cur, $on);

        $invoices = Scope::apply(Invoice::query())->whereIn('status', ['approved', 'sent'])->whereYear('issue_date', now()->year)->get();
        $overdue = $invoices->filter(fn ($i) => $i->displayStatus() === 'Overdue');
        $paymentsToday = Scope::apply(Payment::query())->where('status', 'completed')->whereDate('paid_on', today())->get();

        $taxCountries = $countryId ? Country::whereKey($countryId)->get() : ($user->isGlobal() ? Country::where('is_active', true)->get() : collect([$user->country()])->filter());

        return view('dashboard.index', [
            'taxAlerts' => $user->can('reports.view') ? $tax->alerts($taxCountries) : collect(),
            'currency' => $currency,
            'kpi' => [
                'sales' => $invoices->sum(fn ($i) => $conv($i->total, $i->currency_code, $i->issue_date)),
                'outstanding' => $invoices->sum(fn ($i) => $conv($i->balance(), $i->currency_code)),
                'outstanding_count' => $invoices->filter(fn ($i) => $i->balance() > 0)->count(),
                'overdue' => $overdue->sum(fn ($i) => $conv($i->balance(), $i->currency_code)),
                'overdue_count' => $overdue->count(),
                'payments_today' => $paymentsToday->sum(fn ($p) => $conv($p->amount, $p->currency_code)),
                'payments_today_count' => $paymentsToday->count(),
                'invoice_count' => $invoices->count(),
            ],
            'formsAwaiting' => $approvals->awaiting($user),
            'pendingInvoices' => $user->can('invoices.approve') ? Scope::apply(Invoice::query())->where('status', 'pending_approval')->with('customer')->get() : collect(),
            'advancesOverdue' => Scope::apply(FinanceForm::query())->where('type', 'K')->where('status', 'awaiting_liquidation')->get()
                ->filter(fn ($f) => $f->datum('liquidation_date') < today()->toDateString()),
            'expiringDocs' => Document::whereBetween('expires_on', [today(), today()->addDays(30)])
                ->when($countryId, fn ($q) => $q->whereHas('uploader.branch', fn ($b) => $b->where('country_id', $countryId)))->count(),
            'unread' => $messaging->unreadCount($user),
            'attention' => $overdue->sortBy('due_date')->take(6)->load('customer'),
            'recentPayments' => Scope::apply(Payment::query())->with('customer')->latest('paid_on')->limit(6)->get(),
            'expensesPending' => Scope::apply(Expense::query())->whereIn('status', ['submitted', 'reviewed'])->count(),
        ]);
    }
}
