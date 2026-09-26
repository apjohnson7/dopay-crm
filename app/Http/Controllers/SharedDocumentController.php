<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

/** Public, unguessable link a customer opens from WhatsApp / email. Expires after the configured days. */
class SharedDocumentController extends Controller
{
    public function invoice(string $token)
    {
        $invoice = Invoice::where('share_token', $token)->with('customer', 'items', 'country', 'branch', 'approver')->firstOrFail();
        abort_if($invoice->status === 'cancelled', 410, 'This invoice has been cancelled.');
        $from = $invoice->share_refreshed_at ?? $invoice->sent_at ?? $invoice->generated_at;
        abort_if(! $from || $from->copy()->addDays(config('dopay.share_link_days'))->isPast(), 410, 'This link has expired. Ask us to send the invoice again.');

        return view('invoices.shared', compact('invoice'));
    }
}
