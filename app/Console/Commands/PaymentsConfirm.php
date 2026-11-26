<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payment\MidtransProvider;
use App\Services\Payment\PaymentService;
use App\Support\PaymentMode;
use Illuminate\Console\Command;

class PaymentsConfirm extends Command
{
    protected $signature = 'payments:confirm {reference : nomor PAY-... dari tagihan} {transaction_id? : id transaksi dari dashboard Midtrans}';

    protected $description = 'Konfirmasi manual pembayaran Midtrans lewat API status (tanpa mempercayai isi webhook)';

    public function handle(MidtransProvider $m, PaymentService $svc): int
    {
        if (PaymentMode::isDemo()) {
            $this->error('PAYMENT_MODE=demo. Perintah ini hanya untuk mode gateway.');

            return self::FAILURE;
        }

        $p = Payment::where('payment_reference', $this->argument('reference'))->first();
        if (! $p) {
            $this->error('Tagihan tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $remote = $m->lookup([$this->argument('transaction_id'), $p->provider_reference, $p->payment_reference]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $remote) {
            $this->error('Midtrans tidak menemukan transaksi. Salin Transaction ID dari detail transaksi di dashboard, lalu ulangi.');

            return self::FAILURE;
        }

        $status = $m->statusOf($remote);
        $this->line('Status di Midtrans: '.($remote['transaction_status'] ?? '?').' ('.$status.')');

        if (! $m->matches($remote, $p)) {
            $this->error('Order atau nominal dari Midtrans tidak cocok dengan tagihan. Dibatalkan.');

            return self::FAILURE;
        }
        if ($status === 'pending') {
            $this->warn('Pembayaran belum selesai di Midtrans. Tidak ada perubahan.');

            return self::SUCCESS;
        }

        if (! empty($remote['transaction_id']) && ! $p->provider_reference) {
            $p->update(['provider_reference' => $remote['transaction_id']]);
        }

        $eid = ($remote['transaction_id'] ?? '').'|'.($remote['transaction_status'] ?? '').'|'.($remote['fraud_status'] ?? '');
        $this->info('Hasil: '.$svc->handleResult($p, $status, 'manual_confirm', $eid, $m->safe($remote)));

        return self::SUCCESS;
    }
}