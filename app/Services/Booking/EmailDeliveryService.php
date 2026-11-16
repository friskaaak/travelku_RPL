<?php

namespace App\Services\Booking;

use App\Exceptions\DeliveryException;
use App\Jobs\SendBookingEmail;
use App\Models\Booking;
use App\Models\EmailDelivery;
use Illuminate\Support\Facades\DB;

class EmailDeliveryService
{
    /** Mailer yang hanya mencatat dan tidak mengirim ke inbox. */
    public const NON_DELIVERING = ['log', 'array'];

    public static function mailerIsReal(?string $mailer = null): bool
    {
        return ! in_array($mailer ?? config('mail.default'), self::NON_DELIVERING, true);
    }

    /**
     * Buat baris pelacakan untuk receipt dan tiket. Idempoten (dedupe_key unik).
     * Mengembalikan HANYA id baris yang baru dibuat.
     *
     * @return array<int, int>
     */
    public function createFor(Booking $booking): array
    {
        $receipt = $booking->receipt()->first();
        if (! $receipt) {
            return [];
        }

        $ticket = $booking->tickets()->orderBy('id')->first();
        $ids = [];

        foreach (['receipt', 'ticket'] as $type) {
            if ($type === 'ticket' && ! $ticket) {
                continue;
            }

            $delivery = EmailDelivery::firstOrCreate(
                ['dedupe_key' => "booking:{$booking->id}:{$type}"],
                [
                    'user_id' => $booking->user_id,
                    'booking_id' => $booking->id,
                    'receipt_id' => $receipt->id,
                    'ticket_id' => $type === 'ticket' ? $ticket->id : null,
                    'mail_type' => $type,
                    'recipient' => $booking->contact_email,
                    'status' => EmailDelivery::QUEUED,
                    'attempts' => 0,
                    'mailer' => config('mail.default'),
                ]
            );

            if ($delivery->wasRecentlyCreated) {
                $ids[] = $delivery->id;
            }
        }

        return $ids;
    }

    /** Kirim pekerjaan ke antrean. Kegagalan antrean tidak boleh merusak proses pemanggil. */
    public function dispatch(array $ids): void
    {
        foreach ($ids as $id) {
            try {
                SendBookingEmail::dispatch((int) $id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Kirim ulang memakai baris yang sama (tidak ada baris baru).
     *
     * @throws DeliveryException
     */
    public function resend(EmailDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $row = EmailDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if (in_array($row->status, [EmailDelivery::QUEUED, EmailDelivery::SENDING], true)) {
                throw new DeliveryException('Email ini masih dalam antrean atau sedang dikirim. Tunggu sampai selesai.');
            }

            if ($row->booking->status !== Booking::CONFIRMED) {
                throw new DeliveryException('Pesanan ini tidak lagi terkonfirmasi, sehingga email tidak dapat dikirim ulang.');
            }

            $cooldown = (int) config('travel.mail.resend_cooldown_seconds', 120);
            if ($row->last_attempt_at) {
                $wait = $cooldown - (now()->timestamp - $row->last_attempt_at->timestamp);
                if ($wait > 0) {
                    throw new DeliveryException("Tunggu {$wait} detik sebelum mengirim ulang.");
                }
            }

            $row->update([
                'status' => EmailDelivery::QUEUED,
                'failed_at' => null,
                'error_summary' => null,
            ]);

            // Pekerjaan masuk antrean hanya setelah perubahan status berhasil disimpan.
            $id = $row->id;
            DB::afterCommit(fn () => $this->dispatch([$id]));
        });
    }
}