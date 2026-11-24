<?php

namespace App\Http\Controllers;

use App\Exceptions\CancellationException;
use App\Exceptions\DocumentException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Booking\CancellationService;
use App\Services\Booking\DocumentPdf;
use App\Services\Booking\DocumentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['Pending', 'Confirmed', 'Cancelled', 'Expired'])],
            'service' => ['nullable', Rule::in(['hotel', 'flight', 'train'])],
        ]);

        $bookings = Booking::where('user_id', $request->user()->id)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['service'] ?? null, fn ($q, $v) => $q->where('service_type', $v))
            ->with(['payments' => fn ($q) => $q->latest('id'), 'receipt'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('bookings.index', ['bookings' => $bookings, 'filters' => $filters]);
    }

    public function show(Booking $booking, DocumentService $documents, CancellationService $cancellation)
    {
        Gate::authorize('view', $booking);

        // Pesanan terbayar sebelum Fase 8 mendapat dokumennya di sini (idempoten).
        $documents->ensureIssued($booking);

        $booking->load(['passengers', 'payments' => fn ($q) => $q->latest('id'), 'receipt', 'tickets', 'emailDeliveries']);

        return view('bookings.show', [
            'booking' => $booking,
            'tickets' => $booking->tickets->sortBy('id')->values(),
            'timeline' => $this->timeline($booking),
            'quote' => $cancellation->quote($booking),
        ]);
    }

    public function cancel(Request $request, Booking $booking, CancellationService $cancellation)
    {
        Gate::authorize('view', $booking);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']], [
            'reason.max' => 'Alasan maksimal 200 karakter.',
        ]);

        try {
            $r = $cancellation->cancel($booking, $request->user(), $data['reason'] ?? null);
        } catch (CancellationException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = match ($r['refund_status']) {
            'simulated' => 'Pesanan dibatalkan. Refund '.Money::idr($r['refund']).' dicatat sebagai simulasi (mode demo, tidak ada uang sungguhan).',
            'pending' => 'Pesanan dibatalkan. Refund '.Money::idr($r['refund']).' menunggu diproses admin.',
            default => 'Pesanan dibatalkan. Tidak ada refund karena biaya pembatalan sama dengan total pembayaran.',
        };

        return redirect()->route('bookings.show', $booking->booking_code)->with('success', $msg);
    }

    public function ticketsPdf(Booking $booking, DocumentPdf $pdf)
    {
        Gate::authorize('view', $booking);

        try {
            $out = $pdf->ticketsPdf($booking->tickets()->orderBy('id')->get());
        } catch (DocumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response($out, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tiket-'.$booking->booking_code.'.pdf"',
        ]);
    }

    /** Linimasa dari data yang tercatat. Tidak ada peristiwa karangan. */
    private function timeline(Booking $b): array
    {
        $e = [];
        $add = function ($at, string $icon, string $label, ?string $note = null) use (&$e) {
            if ($at) {
                $e[] = ['at' => $at, 'icon' => $icon, 'label' => $label, 'note' => $note];
            }
        };

        $add($b->created_at, 'bi-plus-circle', 'Pesanan dibuat');

        foreach ($b->payments as $p) {
            $method = config('payment.methods.'.$p->method_code.'.label', $p->method_code);
            $add($p->created_at, 'bi-receipt', "Tagihan {$p->invoice_number} dibuat", $method.($p->is_simulated ? ' (simulasi)' : ''));
            $add($p->paid_at, 'bi-check-circle', 'Pembayaran berhasil', Money::idr($p->amount));

            if (in_array($p->status, [Payment::FAILED, Payment::EXPIRED, Payment::CANCELLED, Payment::RECONCILIATION], true)) {
                $add($p->failed_at ?? $p->updated_at, 'bi-x-circle', "Tagihan {$p->invoice_number}: {$p->status}", $p->failure_reason);
            }
        }

        $add($b->confirmed_at, 'bi-patch-check', 'Pesanan terkonfirmasi');
        $add($b->cancelled_at, 'bi-arrow-counterclockwise', 'Refund dicatat', Money::idr($b->refund_amount).' ('.(['pending' => 'menunggu diproses', 'processed' => 'sudah diproses'][$b->refund_status] ?? 'simulasi').')');

        if ($b->refund_amount > 0) {
            $add($b->cancelled_at, 'bi-arrow-counterclockwise', 'Refund dicatat', Money::idr($b->refund_amount).' ('.($b->refund_status === 'pending' ? 'menunggu diproses' : 'simulasi').')');
        }

        usort($e, fn ($a, $c) => $a['at']->timestamp <=> $c['at']->timestamp);

        return $e;
    }
}
