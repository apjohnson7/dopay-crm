<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Appendices A, B, C, F, G, J, K share one table; type-specific header fields live in `data`.
        Schema::create('finance_forms', function (Blueprint $table) {
            $table->id();
            $table->char('type', 1)->index();
            $table->string('reference', 40)->nullable()->unique();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6);
            $table->enum('status', ['draft', 'in_approval', 'returned', 'approved', 'paid', 'reimbursed', 'awaiting_liquidation', 'liquidated', 'settled', 'void'])->default('draft');
            $table->json('data');
            $table->decimal('total', 18, 2)->default(0);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('prepared_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_note')->nullable();
            $table->foreignId('related_form_id')->nullable()->constrained('finance_forms')->nullOnDelete();
            $table->timestamps();
            $table->index(['country_id', 'type', 'status']);
        });

        Schema::create('finance_form_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->date('line_date')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_inflow')->default(false);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('inflow', 18, 2)->default(0);
            $table->decimal('outflow', 18, 2)->default(0);
            $table->string('account_details')->nullable();
            $table->string('memo_ref', 60)->nullable();
            $table->string('party')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Appendix F budget amounts: one row per category per month.
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained();
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['finance_form_id', 'expense_category_id', 'month']);
        });

        Schema::create('form_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->smallInteger('step_index')->comment('-1 = requester signature');
            $table->string('step_label');
            $table->foreignId('user_id')->constrained();
            $table->string('signer_role');
            $table->timestamp('signed_at');
            $table->string('local_time', 40)->comment('Signer local time with zone label, e.g. 14:10 EAT');
            $table->string('code', 16)->unique()->comment('Signature ID printed on the document');
            $table->string('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->unique(['finance_form_id', 'version', 'step_index']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('category', 60)->nullable();
            $table->json('tags')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('access')->nullable();
            $table->nullableMorphs('documentable');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 40);
            $table->text('body');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Messages sent to customers (invoice, receipt, statement, reminder)
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('kind', 40);
            $table->string('reference', 60)->nullable();
            $table->string('recipient')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('queued');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->smallInteger('offset_days');
            $table->string('channel', 20);
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Internal team messaging between officers in any branch or country
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'group', 'channel']);
            $table->string('name')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('muted')->default(false);
            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->nullableMorphs('attachable');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->string('record_label')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'messages', 'conversation_user', 'conversations', 'reminder_rules', 'communications', 'document_templates', 'documents', 'form_signatures', 'budget_lines', 'finance_form_lines', 'finance_forms'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
