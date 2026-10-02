<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Expense extends Model
{
    use Auditable;

    protected $fillable = ['number', 'country_id', 'branch_id', 'expense_category_id', 'supplier_id', 'description', 'amount', 'input_vat', 'wht_rate', 'wht_amount', 'currency_code', 'exchange_rate', 'spent_on', 'tax_period', 'tax_kind', 'payment_method', 'status', 'source_type', 'source_id', 'submitted_by', 'approved_by'];

    protected $casts = ['spent_on' => 'date', 'amount' => 'decimal:2', 'input_vat' => 'decimal:2', 'wht_rate' => 'decimal:2', 'wht_amount' => 'decimal:2', 'exchange_rate' => 'decimal:6'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
