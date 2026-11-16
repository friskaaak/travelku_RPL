<?php

namespace App\Jobs;

use App\Mail\BookingMail;
use App\Models\EmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim satu email dan mencatat hasilnya.
 *
 * Kebijakan: 3 percobaan, jeda 60 detik lalu 300 detik.
 * Status 'sent' berarti diterima server email (atau, pada mailer log, hanya dicatat di log).
 */
class SendBookingEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $deliveryId)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        // Kunci baris: pekerjaan ganda tidak mengirim dua kali.
        $delivery = DB::transaction(function () {
            $d = EmailDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();

            if (! $d || $d->status === EmailDelivery::SENT) {
                return null; // sudah terkirim (atau baris hilang): tidak ada yang dikerjakan
            }

            $busy = $d->status === EmailDelivery::SENDING
                && $d->last_attempt_at
                && $d->last_attempt_at->gt(now()->subMinutes(5));

            if ($busy) {
                return null; // proses lain sedang mengirim
            }

            $d->update([
                'status' => EmailDelivery::SENDING,
                'attempts' => min(255, $d->attempts + 1),
                'last_attempt_at' => now(),
                'mailer' => config('mail.default'),
            ]);

            return $d;
        });

        if (! $delivery) {
            return;
        }

        try {
            Mail::to($delivery->recipient)->send(new BookingMail($delivery));
        } catch (Throwable $e) {
            // Kembali ke antrean dengan catatan galat. Pengecualian dilempar ulang agar antrean menjadwalkan percobaan berikutnya.
            $delivery->update([
                'status' => EmailDelivery::QUEUED,
                'error_summary' => self::summarize($e, $this->attempts()),
            ]);

            throw $e;
        }

        $delivery->update([
            'status' => EmailDelivery::SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'error_summary' => null,
        ]);
    }

    /** Dipanggil antrean setelah semua percobaan habis. */
    public function failed(Throwable $e): void
    {
        $d = EmailDelivery::find($this->deliveryId);

        if ($d && $d->status !== EmailDelivery::SENT) {
            $d->update([
                'status' => EmailDelivery::FAILED,
                'failed_at' => now(),
                'error_summary' => self::summarize($e, (int) $d->attempts),
            ]);
        }
    }

    /** Ringkasan galat singkat. Kata sandi SMTP disamarkan bila ikut muncul. */
    public static function summarize(Throwable $e, int $attempt): string
    {
        $msg = (string) preg_replace('/\s+/', ' ', $e->getMessage());
        $secret = (string) config('mail.mailers.smtp.password');

        if ($secret !== '') {
            $msg = str_replace($secret, '[disamarkan]', $msg);
        }

        return Str::limit('Percobaan '.$attempt.': '.class_basename($e).': '.$msg, 400);
    }
}