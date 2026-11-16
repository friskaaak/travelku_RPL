<?php

namespace App\Console\Commands;

use App\Services\Booking\EmailDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class MailTest extends Command
{
    protected $signature = 'mail:test';

    protected $description = 'Kirim satu email uji ke alamat MAIL_TEST_RECIPIENT (satu-satunya penerima yang dipakai)';

    public function handle(): int
    {
        $to = (string) config('travel.mail.test_recipient');

        if ($to === '') {
            $this->error('MAIL_TEST_RECIPIENT belum diisi di .env. Isi dengan alamat penerima uji, lalu jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        if (Validator::make(['e' => $to], ['e' => 'email'])->fails()) {
            $this->error('MAIL_TEST_RECIPIENT bukan alamat email yang valid.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $this->line("Pengirim (MAIL_MAILER): {$mailer}");
        $this->line("Penerima uji           : {$to}");

        try {
            Mail::raw(
                "Ini email uji dari ".config('app.name').".\nDikirim pada ".now()->translatedFormat('d F Y, H:i:s')." WIB.\nBila Anda menerima ini, konfigurasi email berfungsi.",
                fn ($m) => $m->to($to)->subject('Uji email '.config('app.name'))
            );
        } catch (\Throwable $e) {
            $secret = (string) config('mail.mailers.smtp.password');
            $msg = $secret !== '' ? str_replace($secret, '[disamarkan]', $e->getMessage()) : $e->getMessage();

            $this->error('GAGAL: '.class_basename($e).': '.$msg);

            return self::FAILURE;
        }

        if (EmailDeliveryService::mailerIsReal($mailer)) {
            $this->info('Server email menerima pesan. Ini BUKAN jaminan sudah masuk inbox. Periksa kotak masuk dan folder spam penerima.');
        } else {
            $this->warn("Pengirim '{$mailer}' hanya mencatat pesan di storage/logs/laravel.log. Tidak ada email yang dikirim ke inbox.");
        }

        return self::SUCCESS;
    }
}