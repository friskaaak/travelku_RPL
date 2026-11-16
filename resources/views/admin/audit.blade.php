@extends('layouts.admin')
@section('title', 'Log Audit')

@section('admin')
<h1 class="h4 mb-3">Log audit</h1>

<div class="tk-card">
    <div class="table-responsive">
        <table class="table tk-table align-middle mb-0">
            <thead>
                <tr><th>Waktu (WIB)</th><th>Admin</th><th>Tindakan</th><th>Objek</th><th>Rincian</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                        <td class="small">{{ $log->user?->name ?? 'sistem' }}</td>
                        <td class="small"><code>{{ $log->action }}</code></td>
                        <td class="small">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '-' }}</td>
                        <td class="small" style="max-width:340px;word-break:break-word">{{ \Illuminate\Support\Str::limit(json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 220) }}</td>
                        <td class="small">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">Belum ada aktivitas tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection