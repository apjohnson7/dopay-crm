<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['code', 'type', 'name', 'company', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'city', 'country_id', 'branch_id', 'id_type', 'id_number', 'registration_no', 'tax_id', 'industry', 'account_manager_id', 'category', 'credit_limit', 'payment_terms_days', 'currency_code'];

    protected $casts = ['date_of_birth' => 'date', 'credit_limit' => 'decimal:2', 'id_number' => 'encrypted'];

    public function displayName(): string
    {
        return $this->company ?: $this->name;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    /** Outstanding, overdue, total purchases and total payments. */
    public function financials(): array
    {
        $live = $this->invoices()->whereIn('status', ['approved', 'sent'])->get();
        $out = $live->sum(fn (Invoice $i) => $i->balance());
        $over = $live->filter(fn (Invoice $i) => $i->displayStatus() === 'Overdue')->sum(fn (Invoice $i) => $i->balance());

        return [
            'outstanding' => $out,
            'overdue' => $over,
            'purchases' => $live->sum('total'),
            'payments' => (float) $this->payments()->where('status', 'completed')->sum('amount'),
        ];
    }
}
