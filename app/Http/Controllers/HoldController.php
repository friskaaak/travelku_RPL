<?php

namespace App\Http\Controllers;

use App\Exceptions\HoldException;
use App\Models\FlightSchedule;
use App\Models\Hold;
use App\Models\Hotel;
use App\Models\TrainSchedule;
use App\Services\Inventory\HoldService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Models\Booking;

class HoldController extends Controller
{
    public function __construct(private HoldService $holds)
    {
    }

    public function storeHotel(Request $request, Hotel $hotel)
    {
        abort_unless($hotel->is_published, 404);

        $data = $request->validate([
            'room_type' => ['required', Rule::in(['Standard', 'Superior', 'Deluxe', 'Family'])],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'between:1,10'],
            'children' => ['required', 'integer', 'between:0,10'],
            'rooms' => ['required', 'integer', 'between:1,5'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'room_type.in' => 'Tipe kamar tidak valid.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
            'adults.between' => 'Jumlah dewasa harus 1 sampai 10.',
            'children.between' => 'Jumlah anak harus 0 sampai 10.',
            'rooms.between' => 'Jumlah kamar harus 1 sampai 5.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        $in = Carbon::createFromFormat('!Y-m-d', $data['check_in']);
        $out = Carbon::createFromFormat('!Y-m-d', $data['check_out']);

        try {
            $hold = $this->holds->createHotelHold(
                $request->user(), $hotel, $data['room_type'], $in, $out,
                (int) $data['rooms'], (int) $data['adults'], (int) $data['children'], $data['promo'] ?? null
            );
        } catch (HoldException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('holds.show', $hold->hold_code)
            ->with('success', 'Kamar berhasil ditahan. Selesaikan pemesanan sebelum waktu habis.');
    }

    public function storeFlight(Request $request, FlightSchedule $schedule)
    {
        $data = $request->validate([
            'passengers' => ['required', 'integer', 'between:1,9'],
            'seats' => ['required', 'array', 'min:1', 'max:9'],
            'seats.*' => ['required', 'string', 'regex:/^\d{1,2}[A-Fa-f]$/', 'distinct:ignore_case'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'seats.required' => 'Pilih kursi terlebih dahulu.',
            'seats.*.regex' => 'Nomor kursi tidak valid.',
            'seats.*.distinct' => 'Ada kursi yang dipilih dua kali.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        if (count($data['seats']) !== (int) $data['passengers']) {
            return back()->with('error', 'Jumlah kursi yang dipilih harus sama dengan jumlah penumpang ('.$data['passengers'].').');
        }

        try {
            $hold = $this->holds->createFlightHold($request->user(), $schedule, $data['seats'], $data['promo'] ?? null);
        } catch (HoldException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('holds.show', $hold->hold_code)
            ->with('success', 'Kursi berhasil ditahan. Selesaikan pemesanan sebelum waktu habis.');
    }

    public function storeTrain(Request $request, TrainSchedule $schedule)
    {
        $data = $request->validate([
            'passengers' => ['required', 'integer', 'between:1,4'],
            'seats' => ['required', 'array', 'min:1', 'max:4'],
            'seats.*' => ['required', 'string', 'regex:/^\d{1,2}[A-Da-d]$/', 'distinct:ignore_case'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'seats.required' => 'Pilih kursi terlebih dahulu.',
            'seats.*.regex' => 'Nomor kursi tidak valid.',
            'seats.*.distinct' => 'Ada kursi yang dipilih dua kali.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        if (count($data['seats']) !== (int) $data['passengers']) {
            return back()->with('error', 'Jumlah kursi yang dipilih harus sama dengan jumlah penumpang ('.$data['passengers'].').');
        }

        try {
            $hold = $this->holds->createTrainHold($request->user(), $schedule, $data['seats'], $data['promo'] ?? null);
        } catch (HoldException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('holds.show', $hold->hold_code)
            ->with('success', 'Kursi berhasil ditahan. Selesaikan pemesanan sebelum waktu habis.');
    }

    public function show(Hold $hold)
    {
        Gate::authorize('view', $hold);

        // Kedaluwarsa dicek di server setiap halaman dibuka.
        $hold = $this->holds->expireIfDue($hold);

        // Pesanan sudah dibuat dan masih menunggu bayar: arahkan ke halaman pembayaran.
        $booking = $hold->booking;
        if ($booking && $booking->status === Booking::PENDING && $hold->status === Hold::ACTIVE) {
            if ($payment = $booking->payments()->latest('id')->first()) {
                return redirect()->route('payments.show', $payment->payment_reference);
            }
        }

        return view('holds.show', [
            'hold' => $hold,
            'productUrl' => $this->productUrl($hold),
        ]);
    }

    /** Dipakai penghitung mundur untuk menyelaraskan dengan waktu server. */
    public function status(Hold $hold)
    {
        Gate::authorize('view', $hold);

        $hold = $this->holds->expireIfDue($hold);

        return response()->json([
            'status' => $hold->status,
            'remaining' => $hold->status === Hold::ACTIVE ? max(0, $hold->expires_at->timestamp - now()->timestamp) : 0,
        ]);
    }

    public function release(Hold $hold)
    {
        Gate::authorize('release', $hold);

        $this->holds->release($hold, Hold::RELEASED, 'Dilepas oleh pelanggan');

        return redirect($this->productUrl($hold))
            ->with('success', 'Penahanan dilepas. Kamar atau kursi kembali tersedia.');
    }

    private function productUrl(Hold $hold): string
    {
        $d = $hold->details;

        return match ($hold->service_type) {
            'hotel' => route('hotels.show', [
                'hotel' => $d['hotel_slug'], 'check_in' => $d['check_in'], 'check_out' => $d['check_out'],
                'adults' => $d['adults'], 'children' => $d['children'], 'rooms' => $d['room_qty'],
            ]),
            'flight' => route('flights.show', ['schedule' => $d['schedule_id'], 'passengers' => $hold->quantity]),
            default => route('trains.show', ['schedule' => $d['schedule_id'], 'passengers' => $hold->quantity]),
        };
    }
}