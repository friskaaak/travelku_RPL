<?php

namespace App\Http\Controllers;

use App\Exceptions\HoldException;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Hold;
use App\Services\Booking\CheckoutService;
use App\Services\Inventory\HoldService;
use App\Services\Payment\PaymentService;
use App\Support\PaymentMode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private HoldService $holds,
        private CheckoutService $checkout,
        private PaymentService $payments,
    ) {
    }

    public function show(Hold $hold)
    {
        Gate::authorize('view', $hold);
        $hold = $this->holds->expireIfDue($hold);

        // Pesanan sudah dibuat dan masih menunggu bayar: lanjutkan ke halaman pembayaran.
        $booking = $hold->booking;
        if ($booking && $booking->status === Booking::PENDING && $hold->status === Hold::ACTIVE) {
            if ($payment = $booking->payments()->latest('id')->first()) {
                return redirect()->route('payments.show', $payment->payment_reference);
            }
        }

        if ($hold->status !== Hold::ACTIVE) {
            return redirect()->route('holds.show', $hold->hold_code);
        }

        return view('checkout.show', [
            'hold' => $hold,
            'categories' => $this->payments->catalog((int) $hold->total_amount),
            'slots' => $this->slots($hold),
            'isDemo' => PaymentMode::isDemo(),
        ]);
    }

    public function store(Request $request, Hold $hold)
    {
        Gate::authorize('view', $hold);
        $hold = $this->holds->expireIfDue($hold);

        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'payment_method' => ['required', Rule::in(PaymentMode::codes())],
            'passengers' => ['required', 'array', 'size:'.(int) $hold->quantity],
            'passengers.*.name' => ['required', 'string', 'max:100', "regex:/^[\pL\s\.'\-]+$/u"],
            'passengers.*.type' => ['nullable', Rule::in(['adult', 'child'])],
            'passengers.*.id_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'terms' => ['accepted'],
        ], [
            'contact_name.required' => 'Nama pemesan wajib diisi.',
            'contact_phone.required' => 'Nomor telepon wajib diisi.',
            'contact_phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, tanda + dan -.',
            'payment_method.required' => 'Pilih metode pembayaran.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
            'passengers.size' => 'Jumlah data tamu atau penumpang tidak sesuai.',
            'passengers.*.name.required' => 'Nama tamu atau penumpang wajib diisi.',
            'passengers.*.name.regex' => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            'passengers.*.id_number.regex' => 'Nomor identitas hanya boleh berisi huruf, angka, dan tanda hubung.',
            'terms.accepted' => 'Anda harus menyetujui ketentuan pembatalan.',
        ]);

        try {
            $payment = $this->checkout->create(
                $hold,
                $request->user(),
                ['name' => $data['contact_name'], 'phone' => $data['contact_phone']],
                $data['passengers'],
                $data['payment_method']
            );
        } catch (PaymentException|HoldException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->with('error', 'Pesanan gagal diproses karena permintaan ganda. Muat ulang halaman lalu coba lagi.');
        }

        return redirect()->route('payments.show', $payment->payment_reference)
            ->with('success', 'Pesanan dibuat. Selesaikan pembayaran sebelum batas waktu.');
    }

    /** Kerangka isian tamu atau penumpang sesuai isi hold. */
    private function slots(Hold $hold): array
    {
        $d = $hold->details;
        $slots = [];

        if ($hold->service_type === 'hotel') {
            foreach ($d['rooms'] as $room) {
                $slots[] = ['label' => 'Tamu utama kamar '.$room['room_number'], 'show_type' => false, 'show_id' => false];
            }

            return $slots;
        }

        foreach ($d['seats'] as $i => $seat) {
            $slots[] = ['label' => 'Penumpang '.($i + 1).', kursi '.$seat['number'], 'show_type' => true, 'show_id' => true];
        }

        return $slots;
    }
}