<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentException;
use App\Models\Receipt;
use App\Services\Booking\DocumentPdf;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt)
    {
        $booking = $receipt->booking;
        Gate::authorize('view', $booking);

        $booking->load('tickets', 'emailDeliveries');

        return view('receipts.show', [
            'receipt' => $receipt,
            's' => $receipt->snapshot,
            'booking' => $booking,
            'tickets' => $booking->tickets->sortBy('id')->values(),
        ]);
    }

    public function pdf(Receipt $receipt, DocumentPdf $pdf)
    {
        Gate::authorize('view', $receipt->booking);

        try {
            $out = $pdf->receipt($receipt);
        } catch (DocumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response($out, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->receipt_number.'.pdf"',
        ]);
    }
}