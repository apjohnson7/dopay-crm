<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 1 accounting core: chart of accounts, double-entry journals, bank and mobile money
 * statement lines for reconciliation, confirmed reconciliations and period locks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained();
            $table->string('code', 10);
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->string('role', 40)->nullable()->comment('Posting role, e.g. ar, vat, bank; null for accounts added by users');
            $table->string('statement_line', 60);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['country_id', 'code']);
            $table->index(['country_id', 'role']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained();
            $table->string('number', 40)->unique();
            $table->date('entry_date')->index();
            $table->string('memo');
            $table->enum('kind', ['auto', 'manual', 'opening', 'revaluation']);
            $table->string('source_key', 80)->nullable()->unique()->comment('Idempotency key for automatic postings, e.g. invoice:12');
            $table->nullableMorphs('source');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['country_id', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->index('ledger_account_id');
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('ledger_account_id')->constrained();
            $table->date('line_date')->index();
            $table->string('description');
            $table->string('reference', 120)->nullable();
            $table->decimal('amount', 18, 2)->comment('Money in positive, money out negative');
            $table->foreignId('journal_line_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->string('import_batch', 40)->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained();
            $table->char('period', 7);
            $table->foreignId('confirmed_by')->constrained('users');
            $table->timestamp('confirmed_at');
            $table->unique(['ledger_account_id', 'period']);
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->char('books_closed_through', 7)->nullable()->after('vat_filing_day')->comment('YYYY-MM: this month and earlier are locked');
        });
    }

    public function down(): void
    {
        Schema::table('countries', fn (Blueprint $t) => $t->dropColumn('books_closed_through'));
        foreach (['reconciliations', 'bank_statement_lines', 'journal_lines', 'journal_entries', 'ledger_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
