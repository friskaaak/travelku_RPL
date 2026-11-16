<?php

return [

    // Berapa hari ke depan jadwal demo dibuat (mulai hari ini).
    'seed_days' => (int) env('TRAVEL_SEED_DAYS', 30),

    // Kapasitas kursi per jadwal. Perubahan hanya berlaku untuk jadwal yang BARU dibuat.
    'flight' => [
        'business_rows' => 3,   // 3 baris x 4 kursi (A C D F) = 12 kursi bisnis
        'economy_rows' => 10,   // 10 baris x 6 kursi (A-F)   = 60 kursi ekonomi
    ],
    'train' => [
        'exec_rows' => 8,       // 8 baris x 4 kursi = 32 kursi eksekutif
        'eco_rows' => 12,       // 12 baris x 4 kursi = 48 kursi ekonomi
    ],

    // Persentase pajak per layanan. Angka DEMONSTRASI, bukan tarif pajak resmi.
    // Ubah sesuai kebutuhan sebelum dipakai sungguhan.
    'tax_percent' => [
        'hotel' => 10,
        'flight' => 0,
        'train' => 0,
    ],

    // Teks kebijakan pembatalan demonstrasi. Aturan yang dapat dikonfigurasi dibuat di Fase 10.
    'cancellation' => [
        'flight' => 'Kebijakan demonstrasi: tiket pesawat tidak dapat dibatalkan setelah pembayaran terverifikasi. Aturan pembatalan dan pengembalian dana dibuat pada Fase 10.',
        'train' => 'Kebijakan demonstrasi: tiket kereta tidak dapat dibatalkan setelah pembayaran terverifikasi. Aturan pembatalan dan pengembalian dana dibuat pada Fase 10.',
    ],

        // Pengaturan email. Alamat uji hanya dipakai oleh perintah mail:test.
    'mail' => [
        'test_recipient' => env('MAIL_TEST_RECIPIENT'),
        'resend_cooldown_seconds' => 120,
    ],
];