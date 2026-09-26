<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The audit trail belongs to a country account too, so each country only sees its own history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['country_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
