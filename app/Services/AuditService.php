<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /** Catat tindakan admin. Dipanggil SETELAH perubahan berhasil disimpan. */
    public static function log(string $action, ?Model $model = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Selisih nilai lama dan baru. Panggil SETELAH fill() dan SEBELUM save().
     *
     * @return array<string, array{dari: mixed, ke: mixed}>
     */
    public static function changes(Model $model): array
    {
        $out = [];

        foreach ($model->getDirty() as $key => $new) {
            $out[$key] = ['dari' => $model->getRawOriginal($key), 'ke' => $new];
        }

        return $out;
    }
}