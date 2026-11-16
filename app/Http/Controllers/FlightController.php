<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightSchedule;
use App\Services\Discount\DiscountService;
use App\Services\Inventory\SeatAvailability;
use App\Services\Inventory\SeatMapBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FlightController extends Controller
{
    private const SLOTS = ['pagi', 'siang', 'sore', 'malam'];

    public function __construct(
        private DiscountService $discounts,
        private SeatAvailability $availability,
        private SeatMapBuilder $maps,
    ) {
    }

    public function index(Request $request)
    {
        $airports = Airport::orderBy('city')->get();
        $airlines = Airline::orderBy('name')->get();
        $maxDate = now()->addDays(max(1, (int) config('travel.seed_days')) - 1)->toDateString();

        [$input, $validator] = $this->parseSearch($request, $airports, $airlines, $maxDate);

        $data = [
            'input' => $input,
            'airports' => $airports,
            'airlines' => $airlines,
            'maxDate' => $maxDate,
            'results' => collect(),
            'soldOut' => 0,
            'routes' => collect(),
            'searched' => false,
        ];

        if ($airports->isEmpty()) {
            return view('flights.index', $data); // data belum di-seed
        }

        if ($validator->fails()) {
            return view('flights.index', $data)->withErrors($validator);
        }

        $origin = $airports->firstWhere('iata', $input['origin']);
        $dest = $airports->firstWhere('iata', $input['destination']);
        $pax = (int) $input['passengers'];
        $cabin = $input['cabin'];
        $user = $request->user();
        $promo = ! empty($input['promo']) ? $this->discounts->normalizeCode($input['promo']) : null;

        $schedules = FlightSchedule::with('flight.airline', 'flight.origin', 'flight.destination')
            ->where('service_date', $input['date'])
            ->where('status', 'scheduled')
            ->where('departure_at', '>', now())
            ->whereHas('flight', fn ($q) => $q->where('is_published', true)
                ->where('origin_airport_id', $origin->id)
                ->where('destination_airport_id', $dest->id))
            ->orderBy('departure_at')
            ->get();

        $counts = $this->availability->flightCounts($schedules->pluck('id'));
        $soldOut = 0;
        $results = collect();

        foreach ($schedules as $s) {
            $f = $s->flight;

            if (! empty($input['airlines']) && ! in_array($f->airline->code, $input['airlines'], true)) {
                continue;
            }
            if (! empty($input['direct_only']) && ! $f->is_direct) {
                continue;
            }
            if (! empty($input['times']) && ! in_array($this->slotOf((int) $s->departure_at->format('G')), $input['times'], true)) {
                continue;
            }

            $unit = $cabin === 'Business' ? $s->business_price : $s->economy_price;
            if ($unit === null) {
                continue; // jadwal ini tidak punya kelas yang dipilih
            }

            $avail = $counts[$s->id][$cabin] ?? 0;
            if ($avail < $pax) {
                $soldOut++;
                continue;
            }

            $original = (int) $unit * $pax;
            $quote = $this->discounts->quote('flight', $original, [
                'flight_scope' => $f->is_international ? 'international' : 'domestic',
            ], $promo, $user);

            $results->push([
                'schedule' => $s,
                'available' => $avail,
                'unit' => (int) $unit,
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
            'duration' => $results->sortBy(fn ($r) => $r['schedule']->flight->duration_minutes),
            default => $results->sortBy(fn ($r) => [$r['has_discount'] ? 0 : 1, $r['schedule']->departure_at->timestamp]),
        };

        $results = $results->values();

        // Bila kosong, tampilkan rute demo yang tersedia sebagai petunjuk.
        $routes = collect();
        if ($results->isEmpty()) {
            $routes = Flight::with('origin', 'destination')->where('is_published', true)->get()
                ->map(fn ($f) => $f->origin->city.' ('.$f->origin->iata.') ke '.$f->destination->city.' ('.$f->destination->iata.')')
                ->unique()->sort()->values();
        }

        return view('flights.index', array_merge($data, [
            'results' => $results,
            'soldOut' => $soldOut,
            'routes' => $routes,
            'searched' => true,
        ]));
    }

    public function show(Request $request, FlightSchedule $schedule)
    {
        $schedule->load('flight.airline', 'flight.origin', 'flight.destination');
        abort_unless($schedule->status === 'scheduled' && $schedule->flight->is_published, 404);

        $user = $request->user();
        $defaults = ['passengers' => 1, 'cabin' => 'Economy'];
        $given = array_filter(
            $request->only(['passengers', 'cabin', 'promo']),
            fn ($v) => is_scalar($v) && $v !== ''
        );
        $input = array_merge($defaults, $given);

        $validator = Validator::make($input, [
            'passengers' => ['required', 'integer', 'between:1,9'],
            'cabin' => ['required', Rule::in(['Economy', 'Business'])],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'passengers.between' => 'Jumlah penumpang harus 1 sampai 9.',
            'passengers.integer' => 'Jumlah penumpang harus berupa angka bulat.',
            'cabin.in' => 'Kelas kabin tidak valid.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        $failed = $validator->fails();
        if ($failed) {
            foreach (array_keys($validator->errors()->messages()) as $key) {
                $input[$key] = $defaults[$key] ?? null; // kembali ke nilai aman
            }
        }

        $pax = (int) $input['passengers'];
        $promo = ! empty($input['promo']) ? $this->discounts->normalizeCode($input['promo']) : null;
        $scope = $schedule->flight->is_international ? 'international' : 'domestic';
        $counts = $this->availability->flightCounts([$schedule->id])[$schedule->id] ?? [];

        $fares = [];
        foreach (['Economy' => $schedule->economy_price, 'Business' => $schedule->business_price] as $cabin => $unit) {
            if ($unit === null) {
                continue;
            }

            $original = (int) $unit * $pax;
            $avail = $counts[$cabin] ?? 0;
            $bookable = $avail >= $pax;
            $quote = $bookable ? $this->discounts->quote('flight', $original, ['flight_scope' => $scope], $promo, $user) : null;

            $fares[] = [
                'cabin' => $cabin,
                'unit' => (int) $unit,
                'original' => $original,
                'final' => $quote['final'] ?? $original,
                'label' => $quote['label'] ?? null,
                'terms' => $quote['terms'] ?? null,
                'available' => $avail,
                'bookable' => $bookable,
                'promo_applied' => (bool) ($quote['promo_discount'] ?? null),
                'promo_error' => $quote['promo_error'] ?? null,
                'notice' => $quote['notice'] ?? null,
            ];
        }

        $promoCheck = null;
        if ($promo) {
            $found = $this->discounts->findValidCode($promo, 'flight', ['flight_scope' => $scope], $user);
            $promoCheck = [
                'ok' => $found['error'] === null,
                'message' => $found['error'] ?? "Kode {$promo} valid. Potongan dihitung untuk tiap kelas di bawah.",
            ];
        }

        $seats = $schedule->seats()->with('hold:id,user_id')->get();
        $map = $this->maps->build($seats, $user?->id, 'cabin_class');

        $view = view('flights.show', [
            'schedule' => $schedule,
            'flight' => $schedule->flight,
            'input' => $input,
            'pax' => $pax,
            'promo' => $promo,
            'promoCheck' => $promoCheck,
            'fares' => $fares,
            'map' => $map,
            'closed' => $schedule->departure_at->isPast(),
        ]);

        return $failed ? $view->withErrors($validator) : $view;
    }

    private function parseSearch(Request $request, $airports, $airlines, string $maxDate): array
    {
        $defaults = [
            'origin' => 'SRG',
            'destination' => 'CGK',
            'date' => now()->addDay()->toDateString(),
            'passengers' => 1,
            'cabin' => 'Economy',
            'sort' => 'recommended',
        ];

        $query = $request->query();
        foreach ($query as $key => $value) {
            if (in_array($key, ['airlines', 'times'], true)) {
                if (! is_array($value)) {
                    unset($query[$key]);
                }
            } elseif (! is_scalar($value) || $value === '') {
                unset($query[$key]);
            }
        }

        // Beranda mengirim teks seperti "Semarang (SRG)". Ambil kode IATA-nya.
        foreach (['origin', 'destination'] as $key) {
            if (isset($query[$key])) {
                $query[$key] = $this->normalizeAirport((string) $query[$key]);
            }
        }

        $input = array_merge($defaults, $query);
        $codes = $airports->pluck('iata')->all();

        $validator = Validator::make($input, [
            'origin' => ['required', Rule::in($codes)],
            'destination' => ['required', Rule::in($codes), 'different:origin'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$maxDate],
            'passengers' => ['required', 'integer', 'between:1,9'],
            'cabin' => ['required', Rule::in(['Economy', 'Business'])],
            'sort' => ['required', Rule::in(['recommended', 'price_asc', 'price_desc', 'departure', 'duration'])],
            'airlines' => ['nullable', 'array'],
            'airlines.*' => ['string', Rule::in($airlines->pluck('code')->all())],
            'times' => ['nullable', 'array'],
            'times.*' => ['string', Rule::in(self::SLOTS)],
            'direct_only' => ['nullable', 'boolean'],
            'discount_only' => ['nullable', 'boolean'],
            'promo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'origin.in' => 'Bandara asal tidak dikenal.',
            'destination.in' => 'Bandara tujuan tidak dikenal.',
            'destination.different' => 'Tujuan harus berbeda dari asal.',
            'date.required' => 'Tanggal berangkat wajib diisi.',
            'date.date_format' => 'Format tanggal tidak valid.',
            'date.after_or_equal' => 'Tanggal berangkat tidak boleh sebelum hari ini.',
            'date.before_or_equal' => 'Jadwal demo hanya tersedia sampai '.$maxDate.'.',
            'passengers.between' => 'Jumlah penumpang harus 1 sampai 9.',
            'passengers.integer' => 'Jumlah penumpang harus berupa angka bulat.',
            'cabin.in' => 'Kelas kabin tidak valid.',
            'sort.in' => 'Pilihan urutan tidak valid.',
            'promo.regex' => 'Kode promo hanya boleh berisi huruf, angka, dan tanda hubung.',
        ]);

        return [$input, $validator];
    }

    private function normalizeAirport(string $value): string
    {
        if (preg_match('/\(([A-Za-z]{3})\)/', $value, $m)) {
            return strtoupper($m[1]);
        }

        return strtoupper(trim($value));
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