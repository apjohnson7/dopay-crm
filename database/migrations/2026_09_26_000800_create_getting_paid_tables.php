<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Roadmap phase 2: getting paid and compliance.
| Quotes and sales orders, recurring invoices, credit notes, online payments (providers, payment requests,
| provider notifications), e-invoicing with the tax authorities, the customer portal, message delivery,
| input VAT and withholding tax on supplier bills, and French.
*/
return new class extends Migration
{
    public function up(): void
    {
        // Quotes and sales orders share one table: a sales order is created from an accepted quote.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->enum('kind', ['quote', 'order'])->default('quote');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6);
            $table->date('issue_date');
            $table->date('valid_until')->nullable()->comment('Quotes only');
            $table->string('status', 20)->default('draft')->comment('quote: draft, sent, accepted, declined, cancelled, converted; order: open, invoiced, cancelled');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('customer_reference', 80)->nullable()->comment('Customer PO number');
            $table->string('access_token', 64)->nullable()->unique()->comment('Lets the customer view and accept the quote without signing in');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_by_name')->nullable()->comment('Name typed by the customer when accepting online');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete()->comment('Staff member who recorded an acceptance');
            $table->text('decline_reason')->nullable();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete()->comment('The quote a sales order came from');
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['country_id', 'kind', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
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

        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('e.g. RI-UG-0001');
            $table->string('name');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->char('currency_code', 3);
            $table->unsignedTinyInteger('every_months')->default(1);
            $table->date('next_run_on')->index();
            $table->date('ends_on')->nullable();
            $table->date('last_run_on')->nullable();
            $table->string('send_channel', 20)->nullable()->comment('Email, WhatsApp, SMS or null to send by hand');
            $table->string('status', 20)->default('pending_approval')->comment('pending_approval, active, paused, finished');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tax_pct', 6, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6);
            $table->date('issue_date');
            $table->string('reason_type', 40);
            $table->text('reason');
            $table->string('status', 20)->default('pending_approval')->comment('pending_approval, approved, rejected');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_applied', 18, 2)->default(0)->comment('Taken off the invoice balance');
            $table->decimal('refund_due', 18, 2)->default(0)->comment('Owed back to the customer because the invoice was already paid');
            $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->string('refund_method', 30)->nullable();
            $table->string('refund_reference', 120)->nullable();
            $table->date('refunded_on')->nullable();
            $table->foreignId('refund_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejected_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['country_id', 'status']);
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
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

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('amount_credited', 18, 2)->default(0)->after('amount_paid');
            $table->foreignId('quote_id')->nullable()->after('terms')->constrained('quotes')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->after('quote_id')->constrained('quotes')->nullOnDelete();
            $table->foreignId('recurring_invoice_id')->nullable()->after('sales_order_id')->constrained()->nullOnDelete();
        });

        // Online payments: one row per provider per country account. Keys are encrypted at rest.
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained();
            $table->string('provider', 30)->comment('mtn_momo, airtel_money, flutterwave, paystack');
            $table->string('mode', 10)->default('disabled')->comment('disabled, test, live');
            $table->text('credentials')->nullable()->comment('Encrypted JSON');
            $table->string('webhook_secret', 255)->nullable()->comment('Encrypted');
            $table->decimal('fee_pct', 5, 2)->default(0)->comment('Provider fee from the contract, posted to bank charges');
            $table->string('settlement_role', 20)->default('clearing')->comment('Ledger account the provider settles to');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('live_at')->nullable();
            $table->foreignId('live_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['country_id', 'provider']);
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Our reference sent to the provider');
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('payment_gateway_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->char('currency_code', 3);
            $table->string('phone', 40)->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, succeeded, failed, expired');
            $table->string('provider_reference', 120)->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('started_from', 20)->default('portal')->comment('portal, link, staff');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 150)->nullable()->comment('Provider event id, or a hash of the body when there is none');
            $table->string('event', 80)->nullable();
            $table->string('reference', 150)->nullable()->index();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('signature_valid')->default(false);
            $table->string('result', 30)->comment('recorded, duplicate, rejected, ignored, failed');
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_gateway_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->decimal('fee', 18, 2)->default(0)->after('payment_gateway_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('received_by')->nullable()->change(); // online payments are recorded by the system
        });

        // E-invoicing: what each invoice or credit note got back from the tax authority.
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('document');
            $table->foreignId('country_id')->constrained();
            $table->string('system', 40)->comment('efris, firs, fne, sandbox');
            $table->string('status', 20)->comment('pending, accepted, rejected, not_submitted');
            $table->boolean('test')->default(true);
            $table->string('authority_number', 120)->nullable()->comment('FDN (URA), IRN (FIRS), FNE number (DGI)');
            $table->string('verification_code', 120)->nullable();
            $table->text('qr_payload')->nullable();
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['document_type', 'document_id']);
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->decimal('wht_rate', 5, 2)->nullable()->after('vat_filing_day')->comment('Usual withholding rate suggested on supplier bills');
            $table->string('einvoice_mode', 10)->default('disabled')->after('wht_rate')->comment('disabled, test, live');
            $table->text('einvoice_credentials')->nullable()->after('einvoice_mode')->comment('Encrypted JSON');
            $table->timestamp('einvoice_connected_at')->nullable()->after('einvoice_credentials');
            $table->string('default_language', 2)->default('en')->after('timezone_label');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('language', 2)->nullable()->after('currency_code')->comment('en or fr; documents and the portal use it');
            $table->timestamp('portal_invited_at')->nullable()->after('language');
            $table->timestamp('portal_last_login_at')->nullable()->after('portal_invited_at');
        });

        // Customer portal sign-in: a one-time link, then a one-time code sent to the customer's phone or email.
        Schema::create('portal_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('channel', 20);
            $table->string('code_hash')->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('communications', function (Blueprint $table) {
            $table->string('provider_message_id', 150)->nullable()->after('status')->index();
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->string('failure_reason')->nullable()->after('read_at');
            $table->foreignId('fallback_of')->nullable()->after('failure_reason')->constrained('communications')->nullOnDelete();
            $table->unsignedTinyInteger('attempts')->default(0)->after('fallback_of');
        });

        // Supplier bills: input VAT is claimed back; withholding tax is kept back from the supplier and paid to the authority.
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('input_vat', 18, 2)->default(0)->after('amount');
            $table->decimal('wht_rate', 5, 2)->default(0)->after('input_vat');
            $table->decimal('wht_amount', 18, 2)->default(0)->after('wht_rate');
            $table->string('tax_kind', 10)->nullable()->after('tax_period')->comment('VAT, WHT or PAYE for Tax-category payments');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->nullable()->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn(['input_vat', 'wht_rate', 'wht_amount', 'tax_kind']));
        Schema::table('communications', function (Blueprint $t) {
            $t->dropConstrainedForeignId('fallback_of');
            $t->dropColumn(['provider_message_id', 'delivered_at', 'read_at', 'failure_reason', 'attempts']);
        });
        Schema::dropIfExists('portal_logins');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['language', 'portal_invited_at', 'portal_last_login_at']));
        Schema::table('countries', fn (Blueprint $t) => $t->dropColumn(['wht_rate', 'einvoice_mode', 'einvoice_credentials', 'einvoice_connected_at', 'default_language']));
        Schema::dropIfExists('fiscal_documents');
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('payment_gateway_id');
            $t->dropColumn('fee');
        });
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payment_gateways');
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recurring_invoice_id');
            $t->dropConstrainedForeignId('sales_order_id');
            $t->dropConstrainedForeignId('quote_id');
            $t->dropColumn('amount_credited');
        });
        foreach (['credit_note_items', 'credit_notes', 'recurring_invoice_items', 'recurring_invoices', 'quote_items', 'quotes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
