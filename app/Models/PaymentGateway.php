<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One online payment provider for one country account. Keys are encrypted at rest and never shown again. */
class PaymentGateway extends Model
{
    use Auditable;

    protected array $auditExclude = ['credentials', 'webhook_secret'];

    protected $fillable = ['country_id', 'provider', 'mode', 'credentials', 'webhook_secret', 'fee_pct', 'settlement_role', 'connected_at', 'live_at', 'live_authorized_by'];

    protected $hidden = ['credentials', 'webhook_secret'];

    protected $casts = ['credentials' => 'encrypted:array', 'webhook_secret' => 'encrypted', 'fee_pct' => 'decimal:2', 'connected_at' => 'datetime', 'live_at' => 'datetime'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function intents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function isEnabled(): bool
    {
        return in_array($this->mode, ['test', 'live'], true);
    }

    public function label(): string
    {
        return config('payments.providers.'.$this->provider.'.label', $this->provider);
    }

    /** Mobile money providers ask the customer to approve on their phone; card providers use a hosted checkout page. */
    public function isMobileMoney(): bool
    {
        return (bool) config('payments.providers.'.$this->provider.'.mobile_money', false);
    }

    public function credential(string $key, $default = null)
    {
        return data_get($this->credentials ?? [], $key, $default);
    }
}
