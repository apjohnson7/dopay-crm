<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceFormLine extends Model
{
    protected $fillable = ['finance_form_id', 'position', 'line_date', 'description', 'expense_category_id', 'is_inflow', 'amount', 'inflow', 'outflow', 'account_details', 'memo_ref', 'party', 'notes'];

    protected $casts = ['line_date' => 'date', 'amount' => 'decimal:2', 'inflow' => 'decimal:2', 'outflow' => 'decimal:2', 'is_inflow' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
