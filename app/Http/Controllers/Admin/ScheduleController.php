<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlightSchedule;
use App\Models\FlightSeat;
use App\Models\TrainSchedule;
use App\Models\TrainSeat;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    private function cfg(string $kind): array
    {
        return match ($kind) {
            'pesawat' => ['model' => FlightSchedule::class, 'with' => ['flight.airline', 'flight.origin', 'flight.destination'], 'label' => 'Pesawat'],
            'kereta' => ['model' => TrainSchedule::class, 'with' => ['train', 'origin', 'destination'], 'label' => 'Kereta'],
            default => abort(404),
        };
    }

    public function index(Request $request, string $kind)
    {
        $c = $this->cfg($kind);
        $f = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['scheduled', 'closed', 'cancelled'])],
        ]);

        $rows = $c['model']::with($c['with'])
            ->withCount(['seats as sold' => fn ($q) => $q->where('status', 'confirmed'), 'seats as held' => fn ($q) => $q->where('status', 'held'), 'seats as total'])
            ->when($f['date'] ?? null, fn ($q, $d) => $q->where('service_date', $d), fn ($q) => $q->where('service_date', '>=', now()->toDateString()))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('departure_at')->paginate(20)->withQueryString();

        return view('admin.schedules.index', ['kind' => $kind, 'label' => $c['label'], 'rows' => $rows, 'f' => $f]);
    }

    public function edit(string $kind, int $id)
    {
        $c = $this->cfg($kind);
        $s = $c['model']::with($c['with'])->withCount(['seats as sold' => fn ($q) => $q->where('status', 'confirmed')])->findOrFail($id);

        return view('admin.schedules.edit', ['kind' => $kind, 'label' => $c['label'], 's' => $s]);
    }

    public function update(Request $request, string $kind, int $id)
    {
        $c = $this->cfg($kind);
        $s = $c['model']::findOrFail($id);

        $rules = ['status' => ['required', Rule::in(['scheduled', 'closed', 'cancelled'])]];
        if ($kind === 'pesawat') {
            $rules['economy_price'] = ['required', 'integer', 'between:10000,200000000'];
            if ($s->business_price !== null) {
                $rules['business_price'] = ['required', 'integer', 'between:10000,500000000'];
            }
        } else {
            $rules['base_fare'] = ['required', 'integer', 'between:10000,50000000'];
        }
        $v = $request->validate($rules, ['*.between' => 'Harga di luar batas yang diizinkan.', '*.integer' => 'Harga harus berupa angka bulat.']);

        $seatModel = $kind === 'pesawat' ? FlightSeat::class : TrainSeat::class;
        $fk = $kind === 'pesawat' ? 'flight_schedule_id' : 'train_schedule_id';

        if ($v['status'] === 'cancelled' && $seatModel::where($fk, $s->id)->where('status', 'confirmed')->exists()) {
            return back()->withInput()->with('error', 'Jadwal ini sudah punya kursi terjual, sehingga tidak bisa dibatalkan. Gunakan status Ditutup.');
        }

        DB::transaction(function () use ($s, $v, $kind, $seatModel, $fk) {
            $s->fill($v);
            $changes = AuditService::changes($s);
            $s->save();

            // Harga baru berlaku hanya untuk kursi yang masih kosong. Hold dan kursi terjual tidak berubah.
            if ($kind === 'pesawat') {
                $seatModel::where($fk, $s->id)->where('status', 'available')->where('cabin_class', 'Economy')->update(['price' => $s->economy_price]);
                if ($s->business_price !== null) {
                    $seatModel::where($fk, $s->id)->where('status', 'available')->where('cabin_class', 'Business')->update(['price' => $s->business_price]);
                }
            } else {
                $seatModel::where($fk, $s->id)->where('status', 'available')->update(['price' => $s->base_fare]);
            }

            AuditService::log('schedule.updated', $s, ['jenis' => $kind, 'perubahan' => $changes]);
        });

        return redirect()->route('admin.schedules.index', $kind)->with('success', 'Jadwal diperbarui. Harga baru berlaku untuk kursi kosong dan hold baru.');
    }
}