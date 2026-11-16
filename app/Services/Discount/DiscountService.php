<?php

namespace App\Services\Discount;

use App\Exceptions\PromoException;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KEBIJAKAN PENUMPUKAN
 *  - Dua diskon hanya digabung bila KEDUANYA is_stackable = true.
 *  - Selain itu, dipilih satu diskon dengan potongan terbesar.
 *  - Total potongan tidak pernah melebihi harga awal.
 *
 * KEBIJAKAN KUOTA
 *  - reserve()      : kuota diambil saat hold dibuat (atomik, di dalam transaksi).
 *  - release()      : kuota dikembalikan bila hold kedaluwarsa / pembayaran gagal.
 *  - markRedeemed() : kuota tetap terpakai setelah pembayaran terverifikasi.
 *
 * Semua uang memakai integer rupiah.
 */
class DiscountService
{
    private ?Collection $automatic = null;

    public function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    /**
     * Diskon otomatis terbaik (satu saja).
     *
     * @return array{discount: ?Discount, amount: int, final: int}
     */
    public function bestAutomatic(string $service, int $amount, array $context = []): array
    {
        $best = null;
        $bestAmount = 0;

        foreach ($this->automaticFor($service) as $discount) {
            if (! $this->matchesScope($discount, $context)) {
                continue;
            }

            $value = $this->calculate($discount, $amount);
            if ($value > $bestAmount) {
                $best = $discount;
                $bestAmount = $value;
            }
        }

        return ['discount' => $best, 'amount' => $bestAmount, 'final' => $amount - $bestAmount];
    }

    /**
     * Validasi kode promo TANPA melihat nominal pesanan.
     * Waktu yang dipakai adalah waktu server.
     *
     * @return array{discount: ?Discount, error: ?string, min_purchase: int}
     */
    public function findValidCode(?string $code, string $service, array $context = [], ?User $user = null): array
    {
        $fail = fn (string $message) => ['discount' => null, 'error' => $message, 'min_purchase' => 0];

        $code = $this->normalizeCode($code);
        if ($code === '') {
            return $fail('Masukkan kode promo.');
        }

        $discount = Discount::with('campaigns')->where('code', $code)->first();

        if (! $discount) {
            return $fail('Kode promo tidak ditemukan.');
        }
        if (! $discount->is_active) {
            return $fail('Kode promo sedang tidak aktif.');
        }
        if ($discount->starts_at->isFuture()) {
            return $fail('Kode promo belum berlaku. Mulai '.$discount->starts_at->translatedFormat('d F Y').'.');
        }
        if ($discount->ends_at->isPast()) {
            return $fail('Kode promo sudah berakhir.');
        }
        if (! in_array($service, $discount->services ?? [], true)) {
            return $fail('Kode promo tidak berlaku untuk layanan ini.');
        }
        if (! $this->matchesScope($discount, $context)) {
            return $fail('Kode promo tidak berlaku untuk pesanan ini.');
        }
        if ($discount->usage_limit !== null && $discount->used_count >= $discount->usage_limit) {
            return $fail('Kuota kode promo sudah habis.');
        }
        if ($user && $discount->per_user_limit !== null && $this->userUsage($discount, $user) >= $discount->per_user_limit) {
            return $fail('Anda sudah mencapai batas pemakaian kode promo ini.');
        }

        $min = (int) $discount->min_purchase;

        // Kode milik kampanye hanya berlaku saat kampanyenya aktif menurut waktu server.
        if ($discount->campaigns->isNotEmpty()) {
            $campaign = $discount->campaigns->first(
                fn ($c) => $c->isActiveNow() && in_array($service, $c->services ?? [], true)
            );
            if (! $campaign) {
                return $fail('Kampanye untuk kode promo ini sedang tidak aktif.');
            }
            $min = max($min, (int) $campaign->min_purchase);
        }

        return ['discount' => $discount, 'error' => null, 'min_purchase' => $min];
    }

    /**
     * Hitung harga akhir. Dipakai halaman pencarian, detail, dan (Fase 6) pembuatan hold.
     *
     * @return array{
     *   base:int, applied:array, discount_total:int, final:int, label:?string, terms:?string,
     *   promo_code:?string, promo_discount:?Discount, promo_error:?string, notice:?string
     * }
     */
    public function quote(string $service, int $amount, array $context = [], ?string $code = null, ?User $user = null): array
    {
        $auto = $this->bestAutomatic($service, $amount, $context);

        $applied = [];
        if ($auto['discount'] && $auto['amount'] > 0) {
            $applied[] = ['discount' => $auto['discount'], 'amount' => $auto['amount']];
        }

        $promoApplied = null;
        $promoError = null;
        $notice = null;

        if ($this->normalizeCode($code) !== '') {
            $check = $this->findValidCode($code, $service, $context, $user);

            if ($check['error']) {
                $promoError = $check['error'];
            } elseif ($amount < $check['min_purchase']) {
                $promoError = 'Minimal transaksi '.Money::idr($check['min_purchase']).' untuk kode ini.';
            } else {
                $promo = $check['discount'];
                $canStack = $auto['discount'] && $auto['discount']->is_stackable && $promo->is_stackable;

                if ($canStack) {
                    $value = $this->calculate($promo, $amount - $auto['amount'], $amount);
                    if ($value > 0) {
                        $applied[] = ['discount' => $promo, 'amount' => $value];
                        $promoApplied = $promo;
                    } else {
                        $promoError = 'Kode ini tidak memberi potongan untuk pesanan Anda.';
                    }
                } else {
                    $value = $this->calculate($promo, $amount);
                    if ($value <= 0) {
                        $promoError = 'Kode ini tidak memberi potongan untuk pesanan Anda.';
                    } elseif ($value > $auto['amount']) {
                        $applied = [['discount' => $promo, 'amount' => $value]]; // menggantikan diskon otomatis
                        $promoApplied = $promo;
                    } else {
                        $notice = 'Diskon otomatis yang lebih besar sudah diterapkan. Kode promo tidak ditumpuk.';
                    }
                }
            }
        }

        $total = min($amount, (int) array_sum(array_column($applied, 'amount')));
        $first = $applied[0]['discount'] ?? null;

        return [
            'base' => $amount,
            'applied' => $applied,
            'discount_total' => $total,
            'final' => $amount - $total,
            'label' => $applied
                ? implode(' + ', array_map(fn ($a) => $a['discount']->label ?? $a['discount']->name, $applied))
                : null,
            'terms' => $first?->terms,
            'promo_code' => $promoApplied?->code,
            'promo_discount' => $promoApplied,
            'promo_error' => $promoError,
            'notice' => $notice,
        ];
    }

    /**
     * Potongan satu diskon (integer). Tidak pernah melebihi $amount.
     * $eligibilityAmount = nilai yang dibandingkan dengan minimal transaksi (bila beda dari $amount).
     */
    public function calculate(Discount $discount, int $amount, ?int $eligibilityAmount = null): int
    {
        if (($eligibilityAmount ?? $amount) < (int) $discount->min_purchase) {
            return 0;
        }

        if ($discount->usage_limit !== null && $discount->used_count >= $discount->usage_limit) {
            return 0;
        }

        $raw = $discount->type === 'percentage'
            ? intdiv($amount * (int) $discount->value, 100)
            : (int) $discount->value;

        if ($discount->max_discount !== null) {
            $raw = min($raw, (int) $discount->max_discount);
        }

        return max(0, min($raw, $amount));
    }

    /**
     * Ambil kuota secara atomik. Panggil di dalam transaksi pembuatan hold (Fase 6).
     *
     * @throws PromoException
     */
    public function reserve(Discount $discount, User $user, int $amount, ?int $holdId = null): DiscountRedemption
    {
        return DB::transaction(function () use ($discount, $user, $amount, $holdId) {
            // Kunci baris diskon: permintaan serentak antre di sini.
            $locked = Discount::whereKey($discount->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active || $locked->starts_at->isFuture() || $locked->ends_at->isPast()) {
                throw new PromoException('Kode promo sudah tidak berlaku.');
            }

            if ($locked->per_user_limit !== null && $this->userUsage($locked, $user) >= $locked->per_user_limit) {
                throw new PromoException('Anda sudah mencapai batas pemakaian kode promo ini.');
            }

            // Penambahan bersyarat: gagal (0 baris) bila kuota sudah penuh.
            $affected = Discount::whereKey($locked->id)
                ->where(function ($q) {
                    $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
                })
                ->increment('used_count');

            if ($affected === 0) {
                throw new PromoException('Kuota kode promo sudah habis.');
            }

            return DiscountRedemption::create([
                'discount_id' => $locked->id,
                'user_id' => $user->id,
                'hold_id' => $holdId,
                'amount' => $amount,
                'status' => 'reserved',
            ]);
        });
    }

    /** Kembalikan kuota (hold kedaluwarsa atau pembayaran gagal). Aman dipanggil berulang. */
    public function release(DiscountRedemption $redemption): void
    {
        DB::transaction(function () use ($redemption) {
            $current = DiscountRedemption::whereKey($redemption->id)->lockForUpdate()->first();

            if (! $current || $current->status !== 'reserved') {
                return; // sudah dilepas atau sudah terpakai
            }

            $current->update(['status' => 'released']);

            Discount::whereKey($current->discount_id)->where('used_count', '>', 0)->decrement('used_count');
        });
    }

    /** Tandai terpakai permanen setelah pembayaran terverifikasi. Idempoten. */
    public function markRedeemed(DiscountRedemption $redemption, int $bookingId): void
    {
        DB::transaction(function () use ($redemption, $bookingId) {
            $current = DiscountRedemption::whereKey($redemption->id)->lockForUpdate()->first();

            if (! $current || $current->status === 'redeemed') {
                return;
            }

            $current->update(['status' => 'redeemed', 'booking_id' => $bookingId]);
        });
    }

    private function userUsage(Discount $discount, User $user): int
    {
        return DiscountRedemption::where('discount_id', $discount->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['reserved', 'redeemed'])
            ->count();
    }

    private function automaticFor(string $service): Collection
    {
        $this->automatic ??= Discount::activeNow()->where('is_automatic', true)->get();

        return $this->automatic->filter(fn (Discount $d) => in_array($service, $d->services ?? [], true));
    }

    private function matchesScope(Discount $discount, array $context): bool
    {
        foreach ($discount->scope_filter ?? [] as $key => $expected) {
            if (($context[$key] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }
}