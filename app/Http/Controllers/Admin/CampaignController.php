<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignRequest;
use App\Models\Campaign;
use App\Models\Discount;
use App\Services\AuditService;
use App\Support\UniqueSlug;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    private const SERVICES = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['draft', 'upcoming', 'active', 'ended'])],
        ]);

        $campaigns = Campaign::query()->with('discounts:id,name,code')
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('promo_code', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => match ($v) {
                'draft' => $q->where('is_published', false),
                'upcoming' => $q->upcoming(),
                'active' => $q->activeNow(),
                'ended' => $q->where('is_published', true)->where('ends_at', '<', now()),
            })
            ->orderByDesc('starts_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.campaigns.index', ['campaigns' => $campaigns, 'filters' => $filters]);
    }

    public function create()
    {
        return view('admin.campaigns.form', $this->formData(new Campaign(), false));
    }

    public function store(CampaignRequest $request)
    {
        $data = $this->payload($request);
        $data['slug'] = UniqueSlug::make($data['name'], Campaign::class);
        $ids = $this->discountIds($request, $data['promo_code']);

        $campaign = DB::transaction(function () use ($data, $ids) {
            $c = Campaign::create($data);
            $c->discounts()->sync($ids);

            return $c;
        });

        if ($request->hasFile('banner')) {
            $campaign->update(['banner_image' => $request->file('banner')->store('campaigns', 'public')]);
        }

        AuditService::log('campaign.created', $campaign, [
            'name' => $campaign->name, 'promo_code' => $campaign->promo_code, 'published' => $campaign->is_published,
        ]);

        return redirect()->route('admin.campaigns.index')
            ->with('success', "Kampanye \"{$campaign->name}\" berhasil dibuat.");
    }

    public function edit(Campaign $campaign)
    {
        return view('admin.campaigns.form', $this->formData($campaign, true));
    }

    public function update(CampaignRequest $request, Campaign $campaign)
    {
        $data = $this->payload($request);
        $old = null;

        if ($request->hasFile('banner')) {
            $old = $campaign->banner_image;
            $data['banner_image'] = $request->file('banner')->store('campaigns', 'public');
        } elseif ($request->boolean('remove_banner')) {
            $old = $campaign->banner_image;
            $data['banner_image'] = null;
        }

        $campaign->fill($data);
        $changes = AuditService::changes($campaign);

        $newIds = $this->discountIds($request, $data['promo_code']);
        $currentIds = $campaign->discounts()->pluck('discounts.id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        if ($currentIds !== $newIds) {
            $changes['diskon_terkait'] = ['dari' => $currentIds, 'ke' => $newIds];
        }

        if ($changes === []) {
            return redirect()->route('admin.campaigns.index')->with('success', 'Tidak ada perubahan yang disimpan.');
        }

        DB::transaction(function () use ($campaign, $newIds) {
            $campaign->save();
            $campaign->discounts()->sync($newIds);
        });

        if ($old && $old !== $campaign->banner_image) {
            Storage::disk('public')->delete($old);
        }

        AuditService::log('campaign.updated', $campaign, ['name' => $campaign->name, 'perubahan' => $changes]);

        return redirect()->route('admin.campaigns.index')->with('success', "Kampanye \"{$campaign->name}\" diperbarui.");
    }

    public function toggle(Campaign $campaign)
    {
        $campaign->update(['is_published' => ! $campaign->is_published]);

        AuditService::log($campaign->is_published ? 'campaign.published' : 'campaign.unpublished', $campaign, [
            'name' => $campaign->name,
        ]);

        return back()->with('success', $campaign->is_published
            ? "Kampanye \"{$campaign->name}\" diterbitkan. Tampil sesuai periodenya."
            : "Kampanye \"{$campaign->name}\" ditarik menjadi draf.");
    }

    public function destroy(Campaign $campaign)
    {
        $snapshot = ['name' => $campaign->name, 'slug' => $campaign->slug, 'id' => $campaign->id];
        $banner = $campaign->banner_image;

        $campaign->delete(); // tautan ke diskon ikut terhapus, diskonnya tetap ada

        if ($banner) {
            Storage::disk('public')->delete($banner);
        }

        AuditService::log('campaign.deleted', null, $snapshot);

        return redirect()->route('admin.campaigns.index')->with('success', "Kampanye \"{$snapshot['name']}\" dihapus.");
    }

    private function formData(Campaign $campaign, bool $isEdit): array
    {
        $discounts = Discount::orderBy('name')->get(['id', 'name', 'code', 'is_automatic']);

        return [
            'campaign' => $campaign,
            'isEdit' => $isEdit,
            'services' => self::SERVICES,
            'discounts' => $discounts,
            'codes' => $discounts->whereNotNull('code'),
            'linked' => $isEdit ? $campaign->discounts()->pluck('discounts.id')->map(fn ($i) => (int) $i)->all() : [],
        ];
    }

    private function payload(CampaignRequest $request): array
    {
        $v = $request->validated();

        return [
            'name' => $v['name'],
            'badge' => $v['badge'] ?? null,
            'description' => $v['description'],
            'starts_at' => Carbon::createFromFormat('Y-m-d\TH:i', $v['starts_at']),
            'ends_at' => Carbon::createFromFormat('Y-m-d\TH:i', $v['ends_at']),
            'services' => array_values(array_unique($v['services'])),
            'min_purchase' => (int) ($v['min_purchase'] ?? 0),
            'promo_code' => $v['promo_code'] ?? null,
            'is_published' => $request->boolean('is_published'),
        ];
    }

    /** Diskon terkait. Diskon pemilik kode promo selalu ikut tertaut. */
    private function discountIds(CampaignRequest $request, ?string $code): array
    {
        $ids = collect($request->input('discount_ids', []))->map(fn ($i) => (int) $i);

        if ($code && ($id = Discount::where('code', $code)->value('id'))) {
            $ids->push((int) $id);
        }

        return $ids->unique()->sort()->values()->all();
    }
}