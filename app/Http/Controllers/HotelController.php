<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\Discount\DiscountService;
use App\Services\Inventory\HotelAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HotelController extends Controller
{
    public const AMENITIES = [
        'Wi-Fi gratis', 'Kolam renang', 'Sarapan', 'AC', 'Parkir gratis',
        'Restoran', 'Spa', 'Pusat kebugaran', 'Antar-jemput bandara',
    ];

    private const STAY_KEYS = ['check_in', 'check_out', 'adults', 'children', 'rooms'];
    private const ROOM_ORDER = ['Standard', 'Superior', 'Deluxe', 'Family'];

    public function __construct(
        private HotelAvailability $availability,
        private DiscountService $discounts,
    ) {
    }

    public function index(Request $request)
    {
        [$input, $validator] = $this->parse($request);

        $data = [
            'input' => $input,
            'stay' => Arr::only($input, self::STAY_KEYS),
            'cities' => Hotel::where('is_published', true)->orderBy('city')->pluck('city')->unique()->values(),
            'amenityOptions' => self::AMENITIES,
            'results' => collect(),
            'nights' => null,
            'soldOut' => 0,
        ];

        if ($validator->fails()) {
            return view('hotels.index', $data)->withErrors($validator);
        }

        [$checkIn, $checkOut, $nights, $roomQty, $perRoom] = $this->stayNumbers($input);

        $query = Hotel::query()->where('is_published', true)
            ->when(! empty($input['destination']), fn ($q) => $q->where('city', $input['destination']))
            ->when(! empty($input['min_rating']), fn ($q) => $q->where('rating', '>=', $input['min_rating']))
            ->when(! empty($input['free_cancel']), fn ($q) => $q->where('free_cancellation', true));

        foreach ($input['amenities'] ?? [] as $amenity) {
            $query->whereJsonContains('amenities', $amenity);
        }

        $hotels = $query->get();

        $rooms = $this->availability
            ->availableRoomsQuery($checkIn, $checkOut, $perRoom)
            ->whereIn('hotel_id', $hotels->pluck('id'))
            ->get()
            ->groupBy('hotel_id');

        $results = $hotels->map(function (Hotel $hotel) use ($rooms, $roomQty, $nights) {
            $available = $rooms->get($hotel->id, collect());
            if ($available->count() < $roomQty) {
                return null; // tidak cukup kamar kosong untuk seluruh tanggal
            }

            $unit = (int) $available->min('base_price');
            $original = $unit * $nights * $roomQty;
            $quote = $this->discounts->bestAutomatic('hotel', $original);

            return [
                'hotel' => $hotel,
                'available' => $available->count(),
                'unit' => $unit,
                'original' => $original,
                'final' => $quote['final'],
                'label' => $quote['discount']?->label,
                'has_discount' => $quote['amount'] > 0,
            ];
        })->filter()->values();

        $soldOut = $hotels->count() - $results->count();

        if (! empty($input['discount_only'])) {
            $results = $results->filter(fn ($r) => $r['has_discount'])->values();
        }

        $results = match ($input['sort']) {
            'price_asc' => $results->sortBy('final'),
            'price_desc' => $results->sortByDesc('final'),
            'rating' => $results->sortByDesc(fn ($r) => [(float) $r['hotel']->rating, $r['hotel']->review_count]),
            default => $results->sortByDesc(fn ($r) => [$r['has_discount'], (float) $r['hotel']->rating, $r['hotel']->review_count]),
        };

        return view('hotels.index', array_merge($data, [
            'results' => $results->values(),
            'nights' => $nights,
            'soldOut' => $soldOut,
        ]));
    }

       public function show(Request $request, Hotel $hotel)
    {
        abort_unless($hotel->is_published, 404);
        $hotel->load('rooms');

        [$input, $validator] = $this->parse($request);
        $promoCode = ! empty($input['promo']) ? $this->discounts->normalizeCode($input['promo']) : null;
        $user = $request->user();

        $data = [
            'hotel' => $hotel,
            'input' => $input,
            'stay' => Arr::only($input, self::STAY_KEYS),
            'images' => $hotel->images ?? [],
            'valid' => false,
            'types' => [],
            'nights' => null,
            'checkIn' => null,
            'checkOut' => null,
            'roomQty' => null,
            'perRoom' => null,
            'promoCode' => $promoCode,
            'promoCheck' => null,
        ];

        if ($validator->fails()) {
            return view('hotels.show', $data)->withErrors($validator);
        }

        [$checkIn, $checkOut, $nights, $roomQty, $perRoom] = $this->stayNumbers($input);

        // Pengecekan kode (tanpa nominal) untuk pesan di bagian atas.
        $promoCheck = null;
        if ($promoCode) {
            $found = $this->discounts->findValidCode($promoCode, 'hotel', [], $user);
            $promoCheck = [
                'ok' => $found['error'] === null,
                'message' => $found['error'] ?? "Kode {$promoCode} valid. Potongan dihitung untuk tiap tipe kamar di bawah.",
            ];
        }

        $available = $this->availability
            ->availableRoomsQuery($checkIn, $checkOut, $perRoom)
            ->where('hotel_id', $hotel->id)
            ->get()
            ->groupBy('room_type');

        $types = [];
        foreach (self::ROOM_ORDER as $type) {
            $group = $hotel->rooms->where('room_type', $type)->where('status', 'active');
            if ($group->isEmpty()) {
                continue;
            }

            $avail = $available->get($type, collect());
            $unit = (int) ($avail->isNotEmpty() ? $avail->min('base_price') : $group->min('base_price'));
            $original = $unit * $nights * $roomQty;
            $bookable = $avail->count() >= $roomQty;
            $quote = $bookable ? $this->discounts->quote('hotel', $original, [], $promoCode, $user) : null;

            $types[] = [
                'type' => $type,
                'total' => $group->count(),
                'available' => $avail->count(),
                'capacity' => (int) $group->max('capacity'),
                'fits' => (int) $group->max('capacity') >= $perRoom,
                'bed' => $group->first()->bed_type,
                'unit' => $unit,
                'original' => $original,
                'final' => $quote['final'] ?? $original,
                'label' => $quote['label'] ?? null,
                'terms' => $quote['terms'] ?? null,
                'promo_applied' => (bool) ($quote['promo_discount'] ?? null),
                'promo_error' => $quote['promo_error'] ?? null,
                'notice' => $quote['notice'] ?? null,
                'bookable' => $bookable,
                'numbers' => $avail->pluck('room_number')->sort()->values()->all(),
            ];
        }

        return view('hotels.show', array_merge($data, [
            'valid' => true,
            'types' => $types,
            'nights' => $nights,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'roomQty' => $roomQty,
            'perRoom' => $perRoom,
            'promoCheck' => $promoCheck,
        ]));
    }

    /** Gabungkan input dengan nilai bawaan, bersihkan, lalu validasi di server. */
    private function parse(Request $request): array
    {
        $defaults = [
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'sort' => 'recommended',
        ];

        $query = $request->query();
        foreach ($query as $key => $value) {
            if ($key === 'amenities') {
                if (! is_array($value)) {
                    unset($query[$key]);
                }
            } elseif (! is_scalar($value) || $value === '') {
                unset($query[$key]);
            }
        }

        $input = array_merge($defaults, $query);

        $validator = Validator::make($input, [
            'destination' => ['nullable', 'string', 'max:60'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'between:1,10'],
            'children' => ['required', 'integer', 'between:0,10'],
            'rooms' => ['required', 'integer', 'between:1,5'],
            'sort' => ['required', Rule::in(['recommended', 'price_asc', 'price_desc', 'rating'])],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', Rule::in(self::AMENITIES)],
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
            'discount_only' => ['nullable', 'boolean'],
            'free_cancel' => ['nullable', 'boolean'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'check_in.required' => 'Tanggal check-in wajib diisi.',
            'check_in.date_format' => 'Format tanggal check-in tidak valid.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'check_out.required' => 'Tanggal check-out wajib diisi.',
            'check_out.date_format' => 'Format tanggal check-out tidak valid.',
            'check_out.after' => 'Tanggal check-out harus setelah check-in.',
            'adults.between' => 'Jumlah dewasa harus 1 sampai 10.',
            'children.between' => 'Jumlah anak harus 0 sampai 10.',
            'rooms.between' => 'Jumlah kamar harus 1 sampai 5.',
            '*.integer' => 'Isian harus berupa angka bulat.',
            'sort.in' => 'Pilihan urutan tidak valid.',
            'amenities.*.in' => 'Pilihan fasilitas tidak valid.',
        ]);

        $validator->after(function ($v) use ($input) {
            if (! $v->errors()->hasAny(['check_in', 'check_out'])) {
                $days = Carbon::parse($input['check_in'])->diffInDays(Carbon::parse($input['check_out']));
                if ($days > 30) {
                    $v->errors()->add('check_out', 'Lama menginap maksimal 30 malam.');
                }
            }
        });

        return [$input, $validator];
    }

    /** Hitung angka menginap dari input yang SUDAH tervalidasi. */
    private function stayNumbers(array $input): array
    {
        $checkIn = Carbon::createFromFormat('!Y-m-d', $input['check_in']);
        $checkOut = Carbon::createFromFormat('!Y-m-d', $input['check_out']);
        $nights = (int) abs($checkIn->diffInDays($checkOut));
        $roomQty = (int) $input['rooms'];
        $guests = (int) $input['adults'] + (int) $input['children'];
        $perRoom = (int) ceil($guests / $roomQty);

        return [$checkIn, $checkOut, $nights, $roomQty, $perRoom];
    }
}