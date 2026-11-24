<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CancellationException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['Pending', 'Confirmed', 'Cancelled', 'Expired'])],
            'service' => ['nullable', Rule::in(['hotel', 'flight', 'train'])],
            'refund' => ['nullable', Rule::in(['pending'])],
        ]);

        $bookings = Booking::query()
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('booking_code', 'like', $like)->orWhere('contact_email', 'like', $like)->orWhere('contact_name', 'like', $like));
            })
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['service'] ?? null, fn ($q, $v) => $q->where('service_type', $v))
            ->when($f['refund'] ?? null, fn ($q) => $q->where('refund_status', 'pending'))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.bookings.index', ['bookings' => $bookings, 'f' => $f]);
    }

    public function show(Booking $booking)
    {
        $booking->load(['payments' => fn ($q) => $q->latest('id'), 'passengers', 'tickets', 'receipt', 'emailDeliveries', 'hold', 'user']);

        return view('admin.bookings.show', ['booking' => $booking]);
    }

    public function refundProcessed(Booking $booking, ReconciliationService $svc, Request $request)
    {
        try {
            $svc->markRefundProcessed($booking, $request->user());
        } catch (CancellationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund ditandai sudah diproses.');
    }
}