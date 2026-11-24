<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\AuditService;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index()
    {
        return view('admin.hotels.index', ['hotels' => Hotel::withCount('rooms')->orderBy('city')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.hotels.form', ['hotel' => new Hotel(), 'isEdit' => false, 'amenityOptions' => \App\Http\Controllers\HotelController::AMENITIES]);
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);
        $data['slug'] = UniqueSlug::make($data['name'], Hotel::class);
        $data['review_count'] = 0;
        $data['images'] = [['label' => 'Lobi', 'icon' => 'bi-building', 'from' => '#7C3AED', 'to' => '#4C1D95']];

        $hotel = Hotel::create($data);
        AuditService::log('hotel.created', $hotel, ['name' => $hotel->name, 'city' => $hotel->city]);

        return redirect()->route('admin.hotels.edit', $hotel)->with('success', 'Hotel dibuat. Tambahkan kamar di bawah agar bisa dipesan.');
    }

    public function edit(Hotel $hotel)
    {
        $hotel->load(['rooms' => fn ($q) => $q->orderBy('room_number')]);

        return view('admin.hotels.form', ['hotel' => $hotel, 'isEdit' => true, 'amenityOptions' => \App\Http\Controllers\HotelController::AMENITIES]);
    }

    public function update(Request $request, Hotel $hotel)
    {
        $hotel->fill($this->payload($request));
        $changes = AuditService::changes($hotel);

        if ($changes === []) {
            return redirect()->route('admin.hotels.index')->with('success', 'Tidak ada perubahan yang disimpan.');
        }

        $hotel->save();
        AuditService::log('hotel.updated', $hotel, ['name' => $hotel->name, 'perubahan' => $changes]);

        return redirect()->route('admin.hotels.index')->with('success', "Hotel \"{$hotel->name}\" diperbarui. Pesanan lama tidak berubah.");
    }

    public function toggle(Hotel $hotel)
    {
        $hotel->update(['is_published' => ! $hotel->is_published]);
        AuditService::log($hotel->is_published ? 'hotel.published' : 'hotel.unpublished', $hotel, ['name' => $hotel->name]);

        return back()->with('success', $hotel->is_published ? 'Hotel ditampilkan.' : 'Hotel disembunyikan dari pencarian. Pesanan lama tidak terpengaruh.');
    }

    private function payload(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'star_rating' => ['required', 'integer', 'between:1,5'],
            'rating' => ['required', 'numeric', 'between:0,5'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', \Illuminate\Validation\Rule::in(\App\Http\Controllers\HotelController::AMENITIES)],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'cancellation_hours' => ['required', 'integer', 'between:1,720'],
            'cancellation_policy' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'name.required' => 'Nama hotel wajib diisi.', 'city.required' => 'Kota wajib diisi.', 'address.required' => 'Alamat wajib diisi.',
            'description.required' => 'Deskripsi wajib diisi.', 'star_rating.between' => 'Bintang harus 1 sampai 5.',
            'rating.between' => 'Rating harus 0 sampai 5.', 'check_in_time.date_format' => 'Format jam check-in HH:MM.',
            'check_out_time.date_format' => 'Format jam check-out HH:MM.', 'cancellation_hours.between' => 'Batas pembatalan 1 sampai 720 jam.',
        ]);

        $free = $request->boolean('free_cancellation');
        $hours = (int) $v['cancellation_hours'];

        return [
            'name' => $v['name'], 'city' => $v['city'], 'address' => $v['address'], 'description' => $v['description'],
            'star_rating' => (int) $v['star_rating'], 'rating' => $v['rating'],
            'amenities' => array_values($v['amenities'] ?? []),
            'check_in_time' => $v['check_in_time'].':00', 'check_out_time' => $v['check_out_time'].':00',
            'free_cancellation' => $free, 'cancellation_hours' => $hours,
            'cancellation_policy' => filled($v['cancellation_policy'] ?? null) ? trim($v['cancellation_policy'])
                : ($free ? "Pembatalan gratis hingga {$hours} jam sebelum waktu check-in. Setelah batas itu, atau tidak datang (no-show), dikenai biaya sebesar tarif satu malam."
                         : 'Tarif ini tidak dapat dibatalkan tanpa biaya. Pembatalan dikenai biaya sebesar tarif satu malam.'),
            'latitude' => $v['latitude'] ?? null, 'longitude' => $v['longitude'] ?? null,
            'is_published' => $request->boolean('is_published'),
        ];
    }
}