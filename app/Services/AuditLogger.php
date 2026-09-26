<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** The country account an entry belongs to: the record's own country, else the acting user's. */
    private static function countryOf(?Model $model): ?int
    {
        if ($model instanceof \App\Models\Country) {
            return $model->id;
        }
        $id = $model?->getAttribute('country_id');
        if (! $id && $model && method_exists($model, 'payment')) {
            $id = $model->payment?->country_id;
        }
        if (! $id && $model instanceof \App\Models\User) {
            $id = $model->countryId();
        }

        return $id ? (int) $id : auth()->user()?->countryId();
    }

    public static function log(string $action, ?Model $model = null, ?array $before = null, ?array $after = null, ?string $label = null): void
    {
        $request = app()->runningInConsole() ? null : request();

        AuditLog::create([
            'user_id' => auth()->id(),
            'country_id' => self::countryOf($model),
            'action' => $action,
            'auditable_type' => $model ? $model->getMorphClass() : null,
            'auditable_id' => $model?->getKey(),
            'record_label' => $label ?? ($model && method_exists($model, 'auditLabel') ? $model->auditLabel() : null),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 250) : 'console',
            'created_at' => now(),
        ]);
    }
}
