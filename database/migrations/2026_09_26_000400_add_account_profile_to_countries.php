<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Each country is its own DoPay account. These details print on that account's invoices, receipts and statements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('address')->nullable()->after('legal_entity');
            $table->string('phone', 40)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('tax_id', 40)->nullable()->after('email');
            $table->text('bank_details')->nullable()->after('tax_id');
            $table->string('mobile_money')->nullable()->after('bank_details');
        });
    }

    public function down(): void
    {
        Schema::table('countries', fn (Blueprint $t) => $t->dropColumn(['address', 'phone', 'email', 'tax_id', 'bank_details', 'mobile_money']));
    }
};
