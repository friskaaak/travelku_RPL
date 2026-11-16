<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DiscountRequest;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\Hold;
use App\Services\AuditService;
use App\Support\DiscountTerms;
use App\Support\UniqueSlug;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    private const SERVICES = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['active', 'scheduled', 'ended', 'inactive', 'exhausted'])],
            'service' => ['nullable', Rule::in(array_keys(self::SERVICES))],
        ]);

        $discounts = Discount::query()
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like));
            })
            ->when($filters['service'] ?? null, fn ($q, $v) => $q->whereJsonContains('services', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $this->applyStatus($q, $v))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        // Diskon yang sudah dipakai tidak boleh dihapus (hanya dinonaktifkan).
        $ids = $discounts->pluck('id');
        $inUse = DiscountRedemption::whereIn('discount_id', $ids)->pluck('discount_id')
            ->merge(Hold::whereIn('discount_id', $ids)->pluck('discount_id'))
            ->merge(Booking::whereIn('discount_id', $ids)->pluck('discount_id'))
            ->unique()->flip();

        return view('admin.discounts.index', [
            'discounts' => $discounts,
            'inUse' => $inUse,
            'filters' => $filters,
            'services' => self::SERVICES,
        ]);
    }

    public function create()
    {
        return view('admin.discounts.form', [
            'discount' => new Discount(),
            'isEdit' => false,
            'services' => self::SERVICES,
        ]);
    }

    public function store(DiscountRequest $request)
    {
        $data = $this->payload($request);
        $data['slug'] = UniqueSlug::make($data['name'], Discount::class);

        $discount = Discount::create($data);

        AuditService::log('discount.created', $discount, [
            'name' => $discount->name, 'code' => $discount->code,
            'type' => $discount->type, 'value' => $discount->value,
        ]);

        return redirect()->route('admin.discounts.index')
            ->with('success', "Diskon \"{$discount->name}\" berhasil dibuat.");
    }

    public function edit(Discount $discount)
    {
        return view('admin.discounts.form', [
            'discount' => $discount,
            'isEdit' => true,
            'services' => self::SERVICES,
        ]);
    }

    public function update(DiscountRequest $request, Discount $discount)
    {
        // Slug tidak berubah agar tautan dan data lama tetap konsisten.
        $discount->fill($this->payload($request));
        $changes = AuditService::changes($discount);

        if ($changes === []) {
            return redirect()->route('admin.discounts.index')->with('success', 'Tidak ada perubahan yang disimpan.');
        }

        $discount->save();
        AuditService::log('discount.updated', $discount, ['name' => $discount->name, 'perubahan' => $changes]);

        return redirect()->route('admin.discounts.index')
            ->with('success', "Diskon \"{$discount->name}\" diperbarui. Perubahan berlaku untuk pesanan baru.");
    }

    public function toggle(Discount $discount)
    {
        $discount->update(['is_active' => ! $discount->is_active]);

        AuditService::log($discount->is_active ? 'discount.activated' : 'discount.deactivated', $discount, [
            'name' => $discount->name, 'code' => $discount->code,
        ]);

        return back()->with('success', $discount->is_active
            ? "Diskon \"{$discount->name}\" diaktifkan."
            : "Diskon \"{$discount->name}\" dinonaktifkan.");
    }

    public function destroy(Discount $discount)
    {
        $used = DiscountRedemption::where('discount_id', $discount->id)->exists()
            || Hold::where('discount_id', $discount->id)->exists()
            || Booking::where('discount_id', $discount->id)->exists();

        if ($used) {
            return back()->with('error', 'Diskon ini sudah pernah dipakai pada pesanan, sehingga tidak dapat dihapus. Nonaktifkan saja.');
        }

        $snapshot = ['name' => $discount->name, 'code' => $discount->code];
        $discount->delete();
        AuditService::log('discount.deleted', null, $snapshot + ['id' => $discount->id]);

        return redirect()->route('admin.discounts.index')->with('success', "Diskon \"{$snapshot['name']}\" dihapus.");
    }

    private function applyStatus($q, string $status)
    {
        return match ($status) {
            'active' => $q->activeNow()->where(fn ($w) => $w->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit')),
            'scheduled' => $q->where('is_active', true)->where('starts_at', '>', now()),
            'ended' => $q->where('is_active', true)->where('ends_at', '<', now()),
            'inactive' => $q->where('is_active', false),
            'exhausted' => $q->activeNow()->whereNotNull('usage_limit')->whereColumn('used_count', '>=', 'usage_limit'),
        };
    }

    /** Ubah data tervalidasi menjadi atribut model. Server yang menentukan, bukan browser. */
    private function payload(DiscountRequest $request): array
    {
        $v = $request->validated();
        $automatic = $request->boolean('is_automatic');
        $services = array_values(array_unique($v['services']));
        $scope = (in_array('flight', $services, true) && ! empty($v['flight_scope']))
            ? ['flight_scope' => $v['flight_scope']] : null;

        $data = [
            'name' => $v['name'],
            'label' => $v['label'] ?? null,
            'description' => $v['description'] ?? null,
            'type' => $v['type'],
            'value' => (int) $v['value'],
            'services' => $services,
            'scope_filter' => $scope,
            'is_automatic' => $automatic,
            'code' => $automatic ? null : ($v['code'] ?? null),
            'min_purchase' => (int) ($v['min_purchase'] ?? 0),
            'max_discount' => ($v['type'] === 'percentage' && ! empty($v['max_discount'])) ? (int) $v['max_discount'] : null,
            'starts_at' => Carbon::createFromFormat('Y-m-d\TH:i', $v['starts_at']),
            'ends_at' => Carbon::createFromFormat('Y-m-d\TH:i', $v['ends_at']),
            'usage_limit' => ($automatic || empty($v['usage_limit'])) ? null : (int) $v['usage_limit'],
            'per_user_limit' => ($automatic || empty($v['per_user_limit'])) ? null : (int) $v['per_user_limit'],
            'is_stackable' => $request->boolean('is_stackable'),
            'is_active' => $request->boolean('is_active'),
        ];

        $data['terms'] = filled($v['terms'] ?? null) ? trim($v['terms']) : DiscountTerms::build($data);

        return $data;
    }
}