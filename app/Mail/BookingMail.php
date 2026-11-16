<?php

namespace App\Mail;

use App\Exceptions\DocumentException;
use App\Models\Booking;
use App\Models\EmailDelivery;
use App\Services\Booking\DocumentPdf;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Satu surat untuk dua jenis: receipt dan tiket (atau voucher hotel).
 * Surat TIDAK diantrekan sendiri: pekerjaan SendBookingEmail yang memanggilnya,
 * sehingga status pengiriman bisa dicatat dengan benar.
 */
class BookingMail extends Mailable
{
    public function __construct(public EmailDelivery $delivery)
    {
    }

    public function envelope(): Envelope
    {
        $b = $this->loadBooking();
        $app = config('app.name');

        if ($this->delivery->mail_type === 'ticket') {
            $doc = $b->service_type === 'hotel' ? 'Voucher hotel' : 'E-ticket';

            return new Envelope(subject: "{$doc} {$b->booking_code} - {$app}");
        }

        return new Envelope(subject: "Bukti pembayaran {$b->booking_code} - {$app}");
    }

    public function content(): Content
    {
        $b = $this->loadBooking();
        $receipt = $b->receipt;

        if (! $receipt) {
            throw new DocumentException('Receipt belum diterbitkan untuk pesanan ini.');
        }

        $s = $receipt->snapshot;
        $type = $this->delivery->mail_type;

        return new Content(
            view: $type === 'ticket' ? 'emails.ticket' : 'emails.receipt',
            with: [
                'booking' => $b,
                's' => $s,
                'lines' => self::productLines($b->service_type, $s['details'] ?? []),
                'tickets' => $b->tickets->sortBy('id')->values(),
                'url' => route('bookings.show', $b->booking_code),
                'isSimulated' => (bool) ($s['payment']['is_simulated'] ?? false),
                'docName' => $b->service_type === 'hotel' ? 'voucher hotel' : 'e-ticket',
            ],
        );
    }

    /** Lampiran PDF dibuat saat surat dirakit. Kegagalan membuat PDF dicatat sebagai kegagalan pengiriman. */
    public function attachments(): array
    {
        $b = $this->loadBooking();
        $pdf = app(DocumentPdf::class);

        if ($this->delivery->mail_type === 'ticket') {
            $tickets = $b->tickets->sortBy('id')->values();

            if ($tickets->isEmpty()) {
                throw new DocumentException('Belum ada tiket untuk dilampirkan.');
            }

            return [
                Attachment::fromData(fn () => $pdf->ticketsPdf($tickets), 'tiket-'.$b->booking_code.'.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        $receipt = $b->receipt;
        if (! $receipt) {
            throw new DocumentException('Receipt belum diterbitkan untuk pesanan ini.');
        }

        return [
            Attachment::fromData(fn () => $pdf->receipt($receipt), $receipt->receipt_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    private function loadBooking(): Booking
    {
        return $this->delivery->booking()->with('receipt', 'tickets')->firstOrFail();
    }

    /**
     * Baris ringkasan produk dari snapshot (label, nilai).
     *
     * @return array<int, array{0:string,1:string}>
     */
    public static function productLines(string $type, array $d): array
    {
        $date = fn ($v) => Carbon::parse($v)->translatedFormat('l, d F Y');
        $time = fn ($v) => Carbon::parse($v)->format('H:i');

        return match ($type) {
            'hotel' => [
                ['Hotel', (string) ($d['title'] ?? '-')],
                ['Alamat', (string) ($d['address'] ?? '-')],
                ['Check-in', $date($d['check_in']).', mulai '.($d['check_in_time'] ?? '14:00')],
                ['Check-out', $date($d['check_out']).', sebelum '.($d['check_out_time'] ?? '12:00')],
                ['Kamar', ($d['room_qty'] ?? 1).' kamar '.($d['room_type'] ?? '').' ('.collect($d['rooms'] ?? [])->pluck('room_number')->implode(', ').'), '.($d['nights'] ?? 0).' malam'],
                ['Tamu', ($d['adults'] ?? 0).' dewasa, '.($d['children'] ?? 0).' anak'],
            ],
            'flight' => [
                ['Penerbangan', ($d['airline'] ?? '').' '.($d['flight_number'] ?? '')],
                ['Rute', ($d['origin']['city'] ?? '').' ('.($d['origin']['iata'] ?? '').') ke '.($d['destination']['city'] ?? '').' ('.($d['destination']['iata'] ?? '').')'],
                ['Berangkat', $date($d['departure_at']).', '.$time($d['departure_at']).' WIB'],
                ['Tiba', $date($d['arrival_at']).', '.$time($d['arrival_at']).' WIB'],
                ['Kursi', collect($d['seats'] ?? [])->pluck('number')->implode(', ')],
            ],
            default => [
                ['Kereta', ($d['train_name'] ?? '').' ('.($d['train_number'] ?? '').'), kelas '.($d['class'] ?? '')],
                ['Rute', ($d['origin']['name'] ?? '').' ke '.($d['destination']['name'] ?? '')],
                ['Berangkat', $date($d['departure_at']).', '.$time($d['departure_at']).' WIB'],
                ['Tiba', $date($d['arrival_at']).', '.$time($d['arrival_at']).' WIB'],
                ['Kursi', collect($d['seats'] ?? [])->pluck('number')->implode(', ')],
            ],
        };
    }
}