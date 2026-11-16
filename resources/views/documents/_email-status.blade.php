@php
    $labels = [
        'queued' => ['Dalam antrean', '#FFFAEB', '#B54708'],
        'sending' => ['Sedang dikirim', '#EFF4FF', '#155EEF'],
        'sent' => ['Diterima server email', '#ECFDF3', '#067647'],
        'failed' => ['Gagal', '#FEF3F2', '#B42318'],
    ];
    $types = ['receipt' => 'Receipt (bukti bayar)', 'ticket' => $booking->service_type === 'hotel' ? 'Voucher hotel' : 'E-ticket'];
@endphp

<div class="tk-card p-3 mb-3 no-print" id="email-status">
    <h2 class="h6 mb-2">Status pengiriman email</h2>

    @forelse ($booking->emailDeliveries->sortBy('id') as $m)
        @php
            [$l, $bg, $fg] = $labels[$m->status] ?? [$m->status, '#F2F4F7', '#475467'];
            $real = \App\Services\Booking\EmailDeliveryService::mailerIsReal($m->mailer);
            if ($m->status === 'sent' && ! $real) {
                $l = 'Dicatat di log lokal';
                $bg = '#F2F4F7';
                $fg = '#475467';
            }
        @endphp
        <div class="border-top py-2 small">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong class="text-dark">{{ $types[$m->mail_type] ?? $m->mail_type }}</strong>
                    ke {{ $m->recipient }}
                </div>
                <span class="badge" style="background:{{ $bg }};color:{{ $fg }}">{{ $l }}</span>
            </div>

            <div class="mt-1">
                Percobaan: {{ $m->attempts }}
                @if ($m->sent_at) · {{ $real ? 'diterima server' : 'dicatat' }} {{ $m->sent_at->translatedFormat('d M Y, H:i') }} WIB @endif
                @if ($m->failed_at) · gagal {{ $m->failed_at->translatedFormat('d M Y, H:i') }} WIB @endif
            </div>

            @if ($m->status === 'sent' && ! $real)
                <div class="mt-1">Mode lokal: pesan hanya ditulis ke <code>storage/logs/laravel.log</code> dan <strong>tidak dikirim ke inbox</strong>.</div>
            @endif

            @if ($m->status === 'queued')
                <div class="mt-1">
                    @if ($m->attempts > 0) Percobaan sebelumnya gagal. Akan dicoba lagi otomatis. @else Menunggu diproses. @endif
                    @if (app()->environment('local')) Pastikan worker berjalan: <code>php artisan queue:work</code>. @endif
                </div>
            @endif

            @if ($m->error_summary)
                <div class="mt-1" style="color:#B42318">{{ $m->error_summary }}</div>
            @endif

            @if (in_array($m->status, ['sent', 'failed'], true))
                <form method="POST" action="{{ route('emails.resend', $m) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Kirim ulang</button>
                </form>
            @endif
        </div>
    @empty
        <p class="small mb-0">Belum ada catatan pengiriman email untuk pesanan ini.</p>
    @endforelse

    <p class="small mt-2 mb-0">Status Diterima server email berarti server email menerima pesan. Itu bukan jaminan sudah masuk inbox atau sudah dibaca. Periksa juga folder spam.</p>
</div>