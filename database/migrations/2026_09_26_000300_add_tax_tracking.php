<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedTinyInteger('vat_filing_day')->default(15)->after('tax_rate')
                ->comment('Day of the following month the monthly VAT/TVA return is due');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->char('tax_period', 7)->nullable()->after('spent_on')->index()
                ->comment('YYYY-MM the tax payment covers (Tax category only)');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn('tax_period'));
        Schema::table('countries', fn (Blueprint $t) => $t->dropColumn('vat_filing_day'));
    }
};
