<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $all = ['hotel', 'flight', 'train'];

        $rows = [
            // ---- Diskon otomatis (tampil di kartu hasil pencarian) ----
            [
                'slug' => 'hotel-hemat-10', 'name' => 'Hemat Hotel 10%', 'label' => 'Hemat 10%',
                'description' => 'Diskon otomatis untuk semua hotel.',
                'type' => 'percentage', 'value' => 10, 'services' => ['hotel'], 'is_automatic' => true,
                'min_purchase' => 300000, 'max_discount' => 250000,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2027-03-31 23:59:59',
            ],
            [
                'slug' => 'pesawat-domestik-7', 'name' => 'Diskon Penerbangan Domestik 7%', 'label' => 'Diskon 7%',
                'description' => 'Diskon otomatis untuk penerbangan domestik.',
                'type' => 'percentage', 'value' => 7, 'services' => ['flight'], 'is_automatic' => true,
                'scope_filter' => ['flight_scope' => 'domestic'],
                'min_purchase' => 300000, 'max_discount' => 100000,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2027-03-31 23:59:59',
            ],
            [
                'slug' => 'pesawat-internasional-5', 'name' => 'Diskon Penerbangan Internasional 5%', 'label' => 'Diskon 5%',
                'description' => 'Diskon otomatis untuk penerbangan internasional.',
                'type' => 'percentage', 'value' => 5, 'services' => ['flight'], 'is_automatic' => true,
                'scope_filter' => ['flight_scope' => 'international'],
                'min_purchase' => 2000000, 'max_discount' => 750000,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2027-03-31 23:59:59',
            ],
            [
                'slug' => 'kereta-hemat-10', 'name' => 'Hemat Kereta 10%', 'label' => 'Hemat 10%',
                'description' => 'Diskon otomatis untuk perjalanan kereta.',
                'type' => 'percentage', 'value' => 10, 'services' => ['train'], 'is_automatic' => true,
                'min_purchase' => 100000, 'max_discount' => 75000,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2027-03-31 23:59:59',
            ],

            // ---- Kode promo (dipakai pada Fase 5) ----
            [
                'slug' => 'kode-liburan15', 'name' => 'Kode Liburan Sekolah', 'label' => 'Kode LIBURAN15', 'code' => 'LIBURAN15',
                'type' => 'percentage', 'value' => 15, 'services' => $all,
                'min_purchase' => 500000, 'max_discount' => 300000, 'usage_limit' => 500, 'per_user_limit' => 2,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2026-12-31 23:59:59',
            ],
            [
                'slug' => 'kode-kereta25k', 'name' => 'Kode Kereta Hemat', 'label' => 'Kode KERETA25K', 'code' => 'KERETA25K',
                'type' => 'fixed', 'value' => 25000, 'services' => ['train'],
                'min_purchase' => 150000, 'usage_limit' => 1000, 'per_user_limit' => 3,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2027-06-30 23:59:59',
            ],
            [
                'slug' => 'kode-selamat-datang', 'name' => 'Kode Selamat Datang', 'label' => 'Kode SELAMATDATANG', 'code' => 'SELAMATDATANG',
                'type' => 'fixed', 'value' => 50000, 'services' => $all,
                'min_purchase' => 400000, 'usage_limit' => 1000, 'per_user_limit' => 1,
                'starts_at' => '2026-10-01 00:00:00', 'ends_at' => '2027-06-30 23:59:59',
            ],
        ];

        // Kode untuk kampanye musiman: [kode, nama, persen, batas potongan, mulai, selesai]
        $seasonal = [
            ['MERDEKA2026', 'Promo Hari Kemerdekaan', 17, 200000, '2026-08-10 00:00:00', '2026-08-20 23:59:59'],
            ['NATAL2026', 'Promo Natal', 10, 300000, '2026-12-01 00:00:00', '2026-12-26 23:59:59'],
            ['TAHUNBARU2027', 'Promo Tahun Baru', 12, 350000, '2026-12-20 00:00:00', '2027-01-05 23:59:59'],
            ['RAMADAN2027', 'Promo Ramadan', 12, 350000, '2027-02-01 00:00:00', '2027-03-09 23:59:59'],
            ['LEBARAN2027', 'Promo Idul Fitri / Lebaran', 15, 500000, '2027-03-01 00:00:00', '2027-03-20 23:59:59'],
        ];

        foreach ($seasonal as [$code, $name, $pct, $max, $start, $end]) {
            $rows[] = [
                'slug' => 'kode-'.strtolower($code), 'name' => $name, 'label' => "Hemat {$pct}%", 'code' => $code,
                'type' => 'percentage', 'value' => $pct, 'services' => $all,
                'min_purchase' => 400000, 'max_discount' => $max, 'usage_limit' => 300, 'per_user_limit' => 1,
                'starts_at' => $start, 'ends_at' => $end,
            ];
        }

        $defaults = [
            'description' => null, 'code' => null, 'is_automatic' => false, 'scope_filter' => null,
            'min_purchase' => 0, 'max_discount' => null, 'usage_limit' => null, 'per_user_limit' => null,
            'is_stackable' => false, 'is_active' => true,
        ];

        foreach ($rows as $row) {
            $d = array_merge($defaults, $row);
            $d['terms'] = $this->terms($d);
            Discount::firstOrCreate(['slug' => $d['slug']], $d);
        }

        $this->command?->info('Diskon contoh siap: '.count($rows).' data.');
    }

    private function terms(array $d): string
    {
        $names = ['hotel' => 'hotel', 'flight' => 'pesawat', 'train' => 'kereta'];
        $services = implode(', ', array_map(fn ($s) => $names[$s], $d['services']));

        $t = ['Berlaku untuk '.$services.'.'];
        if ($d['min_purchase'] > 0) {
            $t[] = 'Minimal transaksi '.Money::idr($d['min_purchase']).'.';
        }
        if (! empty($d['max_discount'])) {
            $t[] = 'Potongan maksimal '.Money::idr($d['max_discount']).'.';
        }
        if (! empty($d['per_user_limit'])) {
            $t[] = 'Maksimal '.$d['per_user_limit'].' kali per akun.';
        }
        $t[] = 'Periode '.Carbon::parse($d['starts_at'])->translatedFormat('d F Y')
            .' sampai '.Carbon::parse($d['ends_at'])->translatedFormat('d F Y').'.';
        $t[] = 'Tidak dapat digabung dengan promo lain.';

        return implode(' ', $t);
    }
}