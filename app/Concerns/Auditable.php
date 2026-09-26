<?php

namespace App\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes create / update / delete events to audit_logs with the before and after values.
 * Models can list fields to keep out of the log in $auditExclude (e.g. encrypted bank details).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $m) {
            AuditLogger::log('Created '.class_basename($m), $m, null, $m->auditFilter($m->getAttributes()));
        });

        static::updated(function (Model $m) {
            $changes = $m->auditFilter($m->getChanges());
            unset($changes['updated_at']);
            if (! $changes) {
                return;
            }
            $before = array_intersect_key($m->getOriginal(), $changes);
            AuditLogger::log('Updated '.class_basename($m), $m, $m->auditFilter($before), $changes);
        });

        static::deleted(function (Model $m) {
            AuditLogger::log('Deleted '.class_basename($m), $m, $m->auditFilter($m->getAttributes()), null);
        });
    }

    public function auditFilter(array $values): array
    {
        $exclude = array_merge(['password', 'remember_token', 'signing_pin', 'two_factor_secret', 'two_factor_recovery_codes', 'created_at'], $this->auditExclude ?? []);

        return array_diff_key($values, array_flip($exclude));
    }

    public function auditLabel(): string
    {
        return $this->number ?? $this->reference ?? $this->code ?? $this->name ?? class_basename($this).' #'.$this->getKey();
    }
}
