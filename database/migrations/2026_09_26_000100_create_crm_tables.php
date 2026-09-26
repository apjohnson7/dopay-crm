<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('e.g. C-UG-0001');
            $table->enum('type', ['business', 'member'])->default('business');
            $table->string('name')->comment('Contact person or member full name');
            $table->string('company')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->string('id_type')->nullable();
            $table->text('id_number')->nullable()->comment('Encrypted');
            $table->string('registration_no')->nullable();
            $table->string('tax_id', 40)->nullable()->index();
            $table->string('industry')->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'name']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('name');
            $table->foreignId('product_category_id')->constrained();
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('Unit');
            $table->decimal('price', 18, 2);
            $table->decimal('cost', 18, 2)->default(0);
            $table->char('currency_code', 3);
            $table->boolean('taxable')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('country_product', function (Blueprint $table) {
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['country_id', 'product_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('company');
            $table->string('contact_person')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('country_id')->constrained();
            $table->string('tax_id', 40)->nullable();
            $table->text('bank_details')->nullable()->comment('Encrypted');
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('supplies')->nullable();
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6)->comment('Units of currency per 1 USD on the issue date');
            $table->date('issue_date');
            $table->date('due_date')->index();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'sent', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tax_pct', 6, 3)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6);
            $table->decimal('amount', 18, 2);
            $table->string('method', 30);
            $table->string('reference', 120)->nullable()->index();
            $table->date('paid_on');
            $table->enum('status', ['completed', 'failed', 'reversed'])->default('completed');
            $table->foreignId('received_by')->constrained('users');
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'paid_on']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('payment_id')->unique()->constrained();
            $table->date('issued_on');
            $table->enum('status', ['issued', 'void'])->default('issued');
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('group', ['recurrent', 'fixed']);
            $table->unsignedTinyInteger('position');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 18, 2);
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6);
            $table->date('spent_on')->index();
            $table->string('payment_method', 30)->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'approved', 'paid', 'rejected'])->default('draft');
            $table->nullableMorphs('source');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['country_id', 'status', 'spent_on']);
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'expense_categories', 'receipts', 'payment_allocations', 'payments', 'invoice_items', 'invoices', 'suppliers', 'country_product', 'products', 'product_categories', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
