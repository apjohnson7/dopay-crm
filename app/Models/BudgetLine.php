<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    protected $fillable = ['finance_form_id', 'expense_category_id', 'month', 'amount', 'note'];

    protected $casts = ['amount' => 'decimal:2'];
}
