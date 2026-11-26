<?php

namespace App\Console\Commands;

use App\Services\Payment\MidtransProvider;
use Illuminate\Console\Command;

class MidtransDiagnose extends Command
{
    protected $signature = 'midtrans:diagnose';

    protected $description = 'Periksa konfigurasi Midtrans tanpa menampilkan rahasia';

    public function handle(MidtransProvider $m): int
    {
        $key = (string) config('payment.midtrans.server_key');
        $fp = fn (string $v) => $v === '' ? '(kosong)' : substr(hash('sha256', $v), 0, 10);

        $this->line('Mode pembayaran       : '.config('payment.mode'));
        $this->line('Sandbox / production  : '.(config('payment.midtrans.is_production') ? 'PRODUCTION' : 'sandbox'));
        $this->line('Panjang kunci         : '.strlen($key).' (kunci server sandbox normal: 38)');
        $this->line('Sidik kunci (Laravel) : '.$fp($key));

        $env = base_path('.env');
        if (is_file($env) && preg_match_all('/^MIDTRANS_SERVER_KEY=(.*)$/m', (string) file_get_contents($env), $mm)) {
            $n = count($mm[1]);
            $val = trim(trim(end($mm[1])), '"\'');
            $this->line("Baris di .env         : {$n}".($n > 1 ? '  <-- DUPLIKAT, baris terakhir yang berlaku' : ''));
            $this->line('Sidik nilai di .env   : '.$fp($val).($fp($val) === $fp($key) ? ' (sama)' : '  <-- BEDA dengan yang dipakai Laravel'));
        }

        $os = getenv('MIDTRANS_SERVER_KEY');
        if ($os !== false && $os !== '') {
            $this->warn('Variabel lingkungan Windows MIDTRANS_SERVER_KEY ADA (sidik '.$fp((string) $os).') dan mengalahkan .env.');
        }

        $this->line('Cache konfigurasi     : '.(is_file(base_path('bootstrap/cache/config.php')) ? 'ADA  <-- jalankan php artisan config:clear' : 'tidak ada'));
        $this->line('Folder proyek ini     : '.base_path());

        try {
            $this->line('Kunci diterima Midtrans: '.($m->probeKey() ? 'YA' : 'TIDAK (401)'));
        } catch (\Throwable $e) {
            $this->error('Gagal menghubungi Midtrans: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}