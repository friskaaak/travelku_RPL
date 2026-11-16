<?php

namespace App\Http\Controllers;

use App\Services\Discount\DiscountService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    /**
     * Cek kelayakan kode promo. Sengaja TIDAK menerima nominal dari browser.
     * Potongan sebenarnya dihitung server saat memilih kamar/tiket/kursi.
     */
    public function check(Request $request, DiscountService $discounts)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'service' => ['required', Rule::in(['hotel', 'flight', 'train'])],
        ], [
            'code.required' => 'Masukkan kode promo.',
            'code.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
            'service.in' => 'Layanan tidak valid.',
        ]);

        $result = $discounts->findValidCode($data['code'], $data['service'], [], $request->user());

        if ($result['error']) {
            return response()->json(['valid' => false, 'message' => $result['error']]);
        }

        $d = $result['discount'];

        return response()->json([
            'valid' => true,
            'message' => 'Kode promo valid. Potongan dihitung saat Anda memilih kamar, tiket, atau kursi.',
            'label' => $d->label ?? $d->name,
            'terms' => $d->terms,
            'min_purchase' => Money::idr($result['min_purchase']),
            'ends_at' => $d->ends_at->translatedFormat('d F Y'),
        ]);
    }
}