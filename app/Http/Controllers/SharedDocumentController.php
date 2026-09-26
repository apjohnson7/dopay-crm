<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

/** Public, unguessable link a customer opens from WhatsApp / email. Expires after the configured days. */
class SharedDocumentController extends Controller
{
    public function invoice(string $token)
    {
        $invoice = Invoice::where('share_token', $token)->with('customer', 'items', 'country', 'branch', 'approver')->firstOrFail();
        abort_if($invoice->generated_at?->addDays(config('dopay.share_link_days'))->isPast() && $invoice->balance() <= 0, 410, 'This link has expired.');

        return view('invoices.shared', compact('invoice'));
    }
}
