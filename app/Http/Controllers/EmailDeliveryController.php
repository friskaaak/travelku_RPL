<?php

namespace App\Http\Controllers;

use App\Exceptions\DeliveryException;
use App\Models\EmailDelivery;
use App\Services\Booking\EmailDeliveryService;
use Illuminate\Support\Facades\Gate;

class EmailDeliveryController extends Controller
{
    public function resend(EmailDelivery $delivery, EmailDeliveryService $emails)
    {
        // Hanya pemilik pesanan.
        Gate::authorize('view', $delivery->booking);

        try {
            $emails->resend($delivery);
        } catch (DeliveryException $e) {
            return back()->with('error', $e->getMessage());
        }

        $note = EmailDeliveryService::mailerIsReal()
            ? 'Email dimasukkan ke antrean untuk dikirim ulang.'
            : 'Email dimasukkan ke antrean. Mode lokal: pesan hanya dicatat di log, tidak dikirim ke inbox.';

        return back()->with('success', $note);
    }
}