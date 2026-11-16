<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentException;
use App\Models\Booking;
use App\Services\Booking\DocumentPdf;
use App\Services\Booking\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['payments' => fn ($q) => $q->latest('id'), 'receipt'])
            ->latest('id')
            ->paginate(10);

        return view('bookings.index', ['bookings' => $bookings]);
    }

    public function show(Booking $booking, DocumentService $documents)
    {
        Gate::authorize('view', $booking);

        // Pesanan terbayar sebelum Fase 8 mendapat dokumennya di sini (idempoten).
        $documents->ensureIssued($booking);

        $booking->load(['passengers', 'payments' => fn ($q) => $q->latest('id'), 'receipt', 'tickets', 'emailDeliveries']);

        return view('bookings.show', [
            'booking' => $booking,
            'tickets' => $booking->tickets->sortBy('id')->values(),
        ]);
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
}