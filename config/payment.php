<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode Pembayaran
    |--------------------------------------------------------------------------
    | demo       : simulasi lokal. TIDAK ada uang sungguhan yang bergerak.
    | production : memakai payment gateway resmi (butuh kredensial valid).
    */
    'mode' => env('PAYMENT_MODE', 'demo'),

    'provider' => env('PAYMENT_PROVIDER', 'midtrans'),

    'currency' => 'IDR',
        // Hanya dipakai saat PAYMENT_MODE=production dan PAYMENT_PROVIDER=midtrans.
        'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        // true = webhook sukses WAJIB dikonfirmasi ke API status (lebih ketat). false = cadangan tanda tangan webhook.
        'strict_confirm' => (bool) env('MIDTRANS_STRICT_CONFIRM', false),
    ],
    

    // Metode yang aktif saat PAYMENT_MODE bukan demo.
    'live_methods' => ['dana'],

    // Batas waktu pembayaran (menit) sejak tagihan dibuat.
    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 60),

    // Batas waktu hold inventori (menit) saat menuju checkout.
    'hold_expiry_minutes' => (int) env('HOLD_EXPIRY_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Status Transaksi (satu set konsisten)
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'pending'                 => 'Pending',
        'paid'                    => 'Paid',
        'failed'                  => 'Failed',
        'expired'                 => 'Expired',
        'cancelled'               => 'Cancelled',
        'refunded'                => 'Refunded',
        'reconciliation_required' => 'Reconciliation Required',
    ],

    /*
    |--------------------------------------------------------------------------
    | Katalog Metode Pembayaran
    |--------------------------------------------------------------------------
    | category : kunci kategori
    | code     : kode unik metode (dipakai di database dan validasi)
    | flow     : jenis instruksi yang ditampilkan
    |            va | ewallet | qris | card | store
    | fee      : biaya admin tetap dalam rupiah (integer)
    | fee_pct  : biaya admin persentase (desimal, contoh 0.7 = 0,7%)
    */
    'categories' => [
        'bank_transfer' => [
            'label' => 'Transfer Bank (Virtual Account)',
            'icon'  => 'bi-bank',
            'note'  => 'Bayar lewat ATM, mobile banking, atau internet banking.',
        ],
        'ewallet' => [
            'label' => 'E-Wallet',
            'icon'  => 'bi-wallet2',
            'note'  => 'Bayar langsung dari saldo aplikasi dompet digital Anda.',
        ],
        'qris' => [
            'label' => 'QRIS',
            'icon'  => 'bi-qr-code',
            'note'  => 'Pindai kode QR dengan aplikasi pembayaran apa pun.',
        ],
        'card' => [
            'label' => 'Kartu Debit/Kredit',
            'icon'  => 'bi-credit-card-2-front',
            'note'  => 'Visa, Mastercard, atau JCB.',
        ],
        'store' => [
            'label' => 'Gerai Retail',
            'icon'  => 'bi-shop',
            'note'  => 'Bayar tunai di kasir gerai terdekat.',
        ],
    ],

    'methods' => [

        // ---- A. Transfer bank / Virtual Account ----
        'bca_va'     => ['category' => 'bank_transfer', 'label' => 'BCA Virtual Account',     'bank' => 'BCA',     'flow' => 'va',     'va_prefix' => '39201', 'fee' => 4000, 'fee_pct' => 0],
        'bri_va'     => ['category' => 'bank_transfer', 'label' => 'BRI Virtual Account',     'bank' => 'BRI',     'flow' => 'va',     'va_prefix' => '26215', 'fee' => 4000, 'fee_pct' => 0],
        'bni_va'     => ['category' => 'bank_transfer', 'label' => 'BNI Virtual Account',     'bank' => 'BNI',     'flow' => 'va',     'va_prefix' => '98802', 'fee' => 4000, 'fee_pct' => 0],
        'mandiri_va' => ['category' => 'bank_transfer', 'label' => 'Mandiri Virtual Account', 'bank' => 'Mandiri', 'flow' => 'va',     'va_prefix' => '88608', 'fee' => 4000, 'fee_pct' => 0],

        // ---- B. E-wallet ----
        'gopay'      => ['category' => 'ewallet', 'label' => 'GoPay',     'flow' => 'ewallet', 'fee' => 0, 'fee_pct' => 2.0],
        'dana'       => ['category' => 'ewallet', 'label' => 'DANA',      'flow' => 'ewallet', 'fee' => 0, 'fee_pct' => 1.5],
        'ovo'        => ['category' => 'ewallet', 'label' => 'OVO',       'flow' => 'ewallet', 'fee' => 0, 'fee_pct' => 1.5],
        'shopeepay'  => ['category' => 'ewallet', 'label' => 'ShopeePay', 'flow' => 'ewallet', 'fee' => 0, 'fee_pct' => 1.5],

        // ---- C. QRIS ----
        'qris'       => ['category' => 'qris', 'label' => 'QRIS', 'flow' => 'qris', 'fee' => 0, 'fee_pct' => 0.7],

        // ---- D. Kartu ----
        'card'       => ['category' => 'card', 'label' => 'Kartu Debit/Kredit', 'flow' => 'card', 'fee' => 0, 'fee_pct' => 2.9],

        // ---- E. Gerai ----
        'alfamart'   => ['category' => 'store', 'label' => 'Alfamart',  'flow' => 'store', 'store_prefix' => 'ALF', 'fee' => 5000, 'fee_pct' => 0],
        'indomaret'  => ['category' => 'store', 'label' => 'Indomaret', 'flow' => 'store', 'store_prefix' => 'IDM', 'fee' => 5000, 'fee_pct' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Jujur untuk Mode Demo
    |--------------------------------------------------------------------------
    | Teks ini DITAMPILKAN di UI agar pengguna tidak mengira pembayaran nyata.
    */
    'demo_labels' => [
        'banner'  => 'MODE DEMO — pembayaran ini hanya simulasi. Tidak ada uang sungguhan yang diproses.',
        'qris'    => 'QR simulasi untuk demo. Ini BUKAN QRIS asli dan tidak dapat dipindai oleh aplikasi pembayaran sungguhan.',
        'va'      => 'Nomor Virtual Account ini hanya contoh simulasi dan tidak terdaftar di bank mana pun.',
        'store'   => 'Kode pembayaran ini hanya simulasi dan tidak dapat dibayar di gerai sungguhan.',
        'card'    => 'Mode demo tidak meminta nomor kartu, CVV, atau PIN. Pilih skenario hasil simulasi di bawah.',
        'ewallet' => 'Simulasi: Anda tidak akan dialihkan ke aplikasi dompet digital sungguhan.',
    ],
];