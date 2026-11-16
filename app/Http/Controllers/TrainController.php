<?php

namespace App\Http\Controllers;

use App\Models\TrainSchedule;
use App\Models\TrainStation;
use App\Services\Discount\DiscountService;
use App\Services\Inventory\SeatAvailability;
use App\Services\Inventory\SeatMapBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrainController extends Controller
{
    private const SLOTS = ['pagi', 'siang', 'sore', 'malam'];
    private const CLASSES = ['Eksekutif', 'Ekonomi'];

    public function __construct(
        private DiscountService $discounts,
        private SeatAvailability $availability,
        private SeatMapBuilder $maps,
    ) {
    }

    public function index(Request $request)
    {
        $cities = TrainStation::orderBy('city')->pluck('city')->unique()->values();
        $maxDate = now()->addDays(max(1, (int) config('travel.seed_days')) - 1)->toDateString();

        [$input, $validator] = $this->parseSearch($request, $cities, $maxDate);

        $data = [
            'input' => $input,
            'cities' => $cities,
            'maxDate' => $maxDate,
            'classes' => self::CLASSES,
            'results' => collect(),
            'soldOut' => 0,
            'searched' => false,
        ];

        if ($cities->isEmpty()) {
            return view('trains.index', $data); // data belum di-seed
        }

        if ($validator->fails()) {
            return view('trains.index', $data)->withErrors($validator);
        }

        $pax = (int) $input['passengers'];
        $user = $request->user();
        $promo = ! empty($input['promo']) ? $this->discounts->normalizeCode($input['promo']) : null;

        $schedules = TrainSchedule::with('train', 'origin', 'destination')
            ->where('service_date', $input['date'])
            ->where('status', 'scheduled')
            ->where('departure_at', '>', now())
            ->whereHas('origin', fn ($q) => $q->where('city', $input['origin']))
            ->whereHas('destination', fn ($q) => $q->where('city', $input['destination']))
            ->orderBy('departure_at')
            ->get();

        $counts = $this->availability->trainCounts($schedules->pluck('id'));
        $soldOut = 0;
        $results = collect();

        foreach ($schedules as $s) {
            if (! empty($input['classes']) && ! in_array($s->class, $input['classes'], true)) {
                continue;
            }
            if (! empty($input['times']) && ! in_array($this->slotOf((int) $s->departure_at->format('G')), $input['times'], true)) {
                continue;
            }

            $avail = $counts[$s->id] ?? 0;
            if ($avail < $pax) {
                $soldOut++;
                continue;
            }

            $original = (int) $s->base_fare * $pax;
            $quote = $s->discount_eligible
                ? $this->discounts->quote('train', $original, [], $promo, $user)
                : ['final' => $original, 'label' => null, 'discount_total' => 0];

            $results->push([
                'schedule' => $s,
                'available' => $avail,
                'original' => $original,
                'final' => $quote['final'],
                'label' => $quote['label'],
                'has_discount' => $quote['discount_total'] > 0,
            ]);
        }

        if (! empty($input['discount_only'])) {
            $results = $results->filter(fn ($r) => $r['has_discount']);
        }

        $results = match ($input['sort']) {
            'price_asc' => $results->sortBy('final'),
            'price_desc' => $results->sortByDesc('final'),
            'departure' => $results->sortBy(fn ($r) => $r['schedule']->departure_at->timestamp),
            'duration' => $results->sortBy(fn ($r) => $r['schedule']->duration_minutes),
            default => $results->sortBy(fn ($r) => [$r['has_discount'] ? 0 : 1, $r['schedule']->departure_at->timestamp]),
        };

        return view('trains.index', array_merge($data, [
            'results' => $results->values(),
            'soldOut' => $soldOut,
            'searched' => true,
        ]));
    }

    public function show(Request $request, TrainSchedule $schedule)
    {
        $schedule->load('train', 'origin', 'destination');
        abort_unless($schedule->status === 'scheduled', 404);

        $user = $request->user();
        $defaults = ['passengers' => 1];
        $given = array_filter($request->only(['passengers', 'promo']), fn ($v) => is_scalar($v) && $v !== '');
        $input = array_merge($defaults, $given);

        $validator = Validator::make($input, [
            'passengers' => ['required', 'integer', 'between:1,4'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'passengers.between' => 'Jumlah penumpang harus 1 sampai 4.',
            'passengers.integer' => 'Jumlah penumpang harus berupa angka bulat.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        $failed = $validator->fails();
        if ($failed) {
            foreach (array_keys($validator->errors()->messages()) as $key) {
                $input[$key] = $defaults[$key] ?? null;
            }
        }

        $pax = (int) $input['passengers'];
        $promo = ! empty($input['promo']) ? $this->discounts->normalizeCode($input['promo']) : null;
        $avail = $this->availability->trainCounts([$schedule->id])[$schedule->id] ?? 0;
        $bookable = $avail >= $pax;
        $original = (int) $schedule->base_fare * $pax;

        $quote = null;
        if ($bookable && $schedule->discount_eligible) {
            $quote = $this->discounts->quote('train', $original, [], $promo, $user);
        }

        $promoCheck = null;
        if ($promo) {
            if (! $schedule->discount_eligible) {
                $promoCheck = ['ok' => false, 'message' => 'Tiket ini tidak termasuk program promo.'];
            } else {
                $found = $this->discounts->findValidCode($promo, 'train', [], $user);
                $promoCheck = [
                    'ok' => $found['error'] === null,
                    'message' => $found['error'] ?? "Kode {$promo} valid. Potongan dihitung di bawah.",
                ];
            }
        }

        $seats = $schedule->seats()->with('hold:id,user_id')->get();
        $map = $this->maps->build($seats, $user?->id, 'seat_class');

        $view = view('trains.show', [
            'schedule' => $schedule,
            'input' => $input,
            'pax' => $pax,
            'promo' => $promo,
            'promoCheck' => $promoCheck,
            'available' => $avail,
            'bookable' => $bookable,
            'original' => $original,
            'final' => $quote['final'] ?? $original,
            'label' => $quote['label'] ?? null,
            'terms' => $quote['terms'] ?? null,
            'promoApplied' => (bool) ($quote['promo_discount'] ?? null),
            'promoError' => $quote['promo_error'] ?? null,
            'notice' => $quote['notice'] ?? null,
            'map' => $map,
            'closed' => $schedule->departure_at->isPast(),
        ]);

        return $failed ? $view->withErrors($validator) : $view;
    }

    private function parseSearch(Request $request, $cities, string $maxDate): array
    {
        $defaults = [
            'origin' => 'Semarang',
            'destination' => 'Jakarta',
            'date' => now()->addDay()->toDateString(),
            'passengers' => 1,
            'sort' => 'recommended',
        ];

        $query = $request->query();
        foreach ($query as $key => $value) {
            if (in_array($key, ['classes', 'times'], true)) {
                if (! is_array($value)) {
                    unset($query[$key]);
                }
            } elseif (! is_scalar($value) || $value === '') {
                unset($query[$key]);
            }
        }

        $input = array_merge($defaults, $query);

        $validator = Validator::make($input, [
            'origin' => ['required', Rule::in($cities->all())],
            'destination' => ['required', Rule::in($cities->all()), 'different:origin'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$maxDate],
            'passengers' => ['required', 'integer', 'between:1,4'],
            'sort' => ['required', Rule::in(['recommended', 'price_asc', 'price_desc', 'departure', 'duration'])],
            'classes' => ['nullable', 'array'],
            'classes.*' => ['string', Rule::in(self::CLASSES)],
            'times' => ['nullable', 'array'],
            'times.*' => ['string', Rule::in(self::SLOTS)],
            'discount_only' => ['nullable', 'boolean'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'origin.in' => 'Kota asal tidak dikenal.',
            'destination.in' => 'Kota tujuan tidak dikenal.',
            'destination.different' => 'Tujuan harus berbeda dari asal.',
            'date.required' => 'Tanggal berangkat wajib diisi.',
            'date.date_format' => 'Format tanggal tidak valid.',
            'date.after_or_equal' => 'Tanggal berangkat tidak boleh sebelum hari ini.',
            'date.before_or_equal' => 'Jadwal demo hanya tersedia sampai '.$maxDate.'.',
            'passengers.between' => 'Jumlah penumpang harus 1 sampai 4.',
            'passengers.integer' => 'Jumlah penumpang harus berupa angka bulat.',
            'sort.in' => 'Pilihan urutan tidak valid.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        return [$input, $validator];
    }

    private function slotOf(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 11 => 'pagi',
            $hour >= 11 && $hour < 15 => 'siang',
            $hour >= 15 && $hour < 19 => 'sore',
            default => 'malam',
        };
    }
}