<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DeliveryException;
use App\Http\Controllers\Controller;
use App\Models\EmailDelivery;
use App\Services\AuditService;
use App\Services\Booking\EmailDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(['queued', 'sending', 'sent', 'failed'])]]);

        $emails = EmailDelivery::with('booking:id,booking_code')
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.emails.index', ['emails' => $emails, 'f' => $f]);
    }

    public function resend(EmailDelivery $delivery, EmailDeliveryService $svc)
    {
        try {
            $svc->resend($delivery);
        } catch (DeliveryException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditService::log('email.resent_by_admin', $delivery, ['booking_id' => $delivery->booking_id, 'type' => $delivery->mail_type]);

        return back()->with('success', 'Email dimasukkan ke antrean untuk dikirim ulang.');
    }
}