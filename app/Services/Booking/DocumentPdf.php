<?php

namespace App\Services\Booking;

use App\Exceptions\DocumentException;
use App\Models\Receipt;
use App\Models\Ticket;
use App\Support\Qr;

/** Membuat PDF. Mengembalikan isi PDF (teks), sehingga Fase 9 bisa memakainya sebagai lampiran email. */
class DocumentPdf
{
    public function receipt(Receipt $receipt): string
    {
        return $this->render('pdf.receipt', [
            'receipt' => $receipt,
            's' => $receipt->snapshot,
        ]);
    }

    /** Satu atau beberapa tiket dalam satu PDF (satu tiket per halaman). */
    public function ticketsPdf(iterable $tickets): string
    {
        $items = collect($tickets)->map(fn (Ticket $t) => [
            'ticket' => $t,
            's' => $t->snapshot,
            'qr' => Qr::dataUri(route('tickets.verify', $t->verification_code), 150),
            'code' => $t->verification_code,
        ]);

        if ($items->isEmpty()) {
            throw new DocumentException('Belum ada tiket untuk dibuat menjadi PDF.');
        }

        return $this->render('pdf.ticket', ['items' => $items]);
    }

    private function render(string $view, array $data): string
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new DocumentException('Pustaka PDF belum terpasang. Jalankan: composer require barryvdh/laravel-dompdf');
        }

        try {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data)->setPaper('a4')->output();
        } catch (\Throwable $e) {
            report($e);

            throw new DocumentException('PDF gagal dibuat. Periksa folder storage/fonts dan ekstensi PHP gd.');
        }
    }
}