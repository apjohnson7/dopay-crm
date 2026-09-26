<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso2', 2)->unique();
            $table->string('name');
            $table->string('doc_code', 6)->comment('Code used in form references, e.g. UGD, NG, CMR');
            $table->string('legal_entity');
            $table->char('currency_code', 3);
            $table->string('tax_name', 10)->default('VAT');
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->string('timezone', 64)->default('UTC');
            $table->string('timezone_label', 8)->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained();
            $table->string('code', 10)->comment('Branch code used in voucher numbers, e.g. HQ, IK');
            $table->string('name');
            $table->string('office')->nullable();
            $table->string('department')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['country_id', 'code']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency_code', 3);
            $table->decimal('rate_per_usd', 18, 6);
            $table->date('effective_on');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['currency_code', 'effective_on']);
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64)->unique()->comment('e.g. INV:UG, FORM:A:UGD, FORM:B:UGD:HQ');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
