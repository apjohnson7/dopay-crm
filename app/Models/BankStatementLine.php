<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = ['country_id', 'ledger_account_id', 'line_date', 'description', 'reference', 'amount', 'journal_line_id', 'matched_by', 'matched_at', 'import_batch', 'imported_by'];

    protected $casts = ['line_date' => 'date', 'amount' => 'decimal:2', 'matched_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}
