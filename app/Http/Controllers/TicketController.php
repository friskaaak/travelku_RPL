<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Booking\DocumentPdf;
use App\Support\Qr;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function show(Ticket $ticket)
    {
        $booking = $ticket->booking;
        Gate::authorize('view', $booking);

        $verifyUrl = route('tickets.verify', $ticket->verification_code);

        return view('tickets.show', [
            'ticket' => $ticket,
            'delivery' => $booking->emailDeliveries()->where('mail_type', 'ticket')->first(),
            's' => $ticket->snapshot,
            'booking' => $booking,
            'qr' => Qr::inline($verifyUrl, 170),
            'verifyUrl' => $verifyUrl,
        ]);
    }

    public function pdf(Ticket $ticket, DocumentPdf $pdf)
    {
        Gate::authorize('view', $ticket->booking);

        try {
            $out = $pdf->ticketsPdf([$ticket]);
        } catch (DocumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response($out, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$ticket->ticket_number.'.pdf"',
        ]);
    }

    /**
     * Halaman verifikasi PUBLIK. Hanya menampilkan data minimum dengan nama tersamar.
     * Kode 40 karakter acak, dibatasi laju lewat rute.
     */
    public function verify(string $code)
    {
        $ticket = Ticket::with('booking.payments')->where('verification_code', $code)->first();

        if (! $ticket) {
            return response()->view('tickets.verify', ['ticket' => null, 'valid' => false], 404);
        }

        $valid = $ticket->booking->status === Booking::CONFIRMED
            && $ticket->booking->payments->contains('status', Payment::PAID);

        return view('tickets.verify', ['ticket' => $ticket, 'valid' => $valid]);
    }
}