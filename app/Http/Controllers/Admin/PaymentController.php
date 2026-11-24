<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CancellationException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Booking\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['Pending', 'Paid', 'Failed', 'Expired', 'Cancelled', 'Refunded', 'Reconciliation Required'])],
        ]);

        $payments = Payment::with('booking:id,booking_code,contact_email')
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('payment_reference', 'like', $like)->orWhere('invoice_number', 'like', $like));
            })
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.payments.index', ['payments' => $payments, 'f' => $f]);
    }

    public function show(Payment $payment)
    {
        $payment->load(['booking.user', 'events' => fn ($q) => $q->latest('id')]);

        return view('admin.payments.show', ['payment' => $payment]);
    }

    public function refund(Request $request, Payment $payment, ReconciliationService $svc)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:200']], ['note.max' => 'Catatan maksimal 200 karakter.']);

        try {
            $svc->refund($payment, $request->user(), $data['note'] ?? null);
        } catch (CancellationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund penuh dicatat dan rekonsiliasi diselesaikan.');
    }
}