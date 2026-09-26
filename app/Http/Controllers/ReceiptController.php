<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt)
    {
        $receipt->load('payment.customer', 'payment.allocations.invoice', 'payment.branch.country', 'payment.receiver');
        $this->ensureCountry($receipt->payment->country_id);

        return view('receipts.show', compact('receipt'));
    }

    public function pdf(Receipt $receipt)
    {
        $receipt->load('payment.customer', 'payment.allocations.invoice', 'payment.branch.country', 'payment.receiver');
        $this->ensureCountry($receipt->payment->country_id);

        return Pdf::loadView('receipts.pdf', compact('receipt'))->setPaper('a4')->download($receipt->number.'.pdf');
    }
}
