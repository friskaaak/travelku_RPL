<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomNight;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function store(Request $request, Hotel $hotel)
    {
        $v = $request->validate([
            'room_number' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('rooms', 'room_number')->where('hotel_id', $hotel->id)],
            'room_type' => ['required', Rule::in(['Standard', 'Superior', 'Deluxe', 'Family'])],
            'capacity' => ['required', 'integer', 'between:1,10'],
            'bed_type' => ['nullable', 'string', 'max:40'],
            'base_price' => ['required', 'integer', 'between:50000,50000000'],
        ], [
            'room_number.unique' => 'Nomor kamar sudah dipakai di hotel ini.',
            'room_number.regex' => 'Nomor kamar hanya huruf, angka, dan tanda hubung.',
            'base_price.between' => 'Harga per malam harus Rp50.000 sampai Rp50.000.000.',
        ]);

        $room = Room::create($v + ['hotel_id' => $hotel->id, 'status' => 'active']);
        AuditService::log('room.created', $room, ['hotel' => $hotel->name, 'room' => $room->room_number]);

        return back()->with('success', "Kamar {$room->room_number} ditambahkan.");
    }

    public function update(Request $request, Room $room)
    {
        $v = $request->validate([
            'capacity' => ['required', 'integer', 'between:1,10'],
            'bed_type' => ['nullable', 'string', 'max:40'],
            'base_price' => ['required', 'integer', 'between:50000,50000000'],
            'status' => ['required', Rule::in(['active', 'maintenance'])],
        ], ['base_price.between' => 'Harga per malam harus Rp50.000 sampai Rp50.000.000.']);

        if ($v['status'] === 'maintenance' && $room->status !== 'maintenance'
            && RoomNight::where('room_id', $room->id)->where('night', '>=', now()->toDateString())->exists()) {
            return back()->with('error', "Kamar {$room->room_number} masih punya malam terjual atau ditahan. Tidak bisa dijadikan perawatan.");
        }

        $room->fill($v);
        $changes = AuditService::changes($room);

        if ($changes !== []) {
            $room->save();
            AuditService::log('room.updated', $room, ['room' => $room->room_number, 'perubahan' => $changes]);
        }

        return back()->with('success', $changes ? "Kamar {$room->room_number} diperbarui. Harga baru berlaku untuk hold baru." : 'Tidak ada perubahan.');
    }
}