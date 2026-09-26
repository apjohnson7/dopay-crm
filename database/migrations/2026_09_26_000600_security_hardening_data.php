<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Follow-up to the security review:
 *  - users.pin_set_at: PINs set before the 6-digit rule must be replaced (users are sent to Security);
 *  - invoices.share_refreshed_at: when a customer link was last re-sent (links expire 30 days after that);
 *  - existing share tokens are rotated, because older audit entries may contain them;
 *  - existing audit entries get the country of the record (or of the person) they belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('pin_set_at')->nullable()->after('signing_pin'));
        Schema::table('invoices', fn (Blueprint $t) => $t->timestamp('share_refreshed_at')->nullable()->after('sent_at'));

        DB::table('invoices')->whereNotNull('share_token')->orderBy('id')->each(function ($row) {
            DB::table('invoices')->where('id', $row->id)->update(['share_token' => Str::random(40)]);
        });

        $withCountry = ['invoice' => 'invoices', 'customer' => 'customers', 'payment' => 'payments', 'expense' => 'expenses', 'supplier' => 'suppliers', 'finance_form' => 'finance_forms'];
        DB::table('audit_logs')->whereNull('country_id')->orderBy('id')->each(function ($log) use ($withCountry) {
            $country = null;
            $type = $log->auditable_type ? Str::snake(class_basename($log->auditable_type)) : null;
            if ($type && isset($withCountry[$type])) {
                $country = DB::table($withCountry[$type])->where('id', $log->auditable_id)->value('country_id');
            } elseif ($type === 'country') {
                $country = $log->auditable_id;
            }
            if (! $country && $log->user_id) {
                $country = DB::table('users')->join('branches', 'branches.id', '=', 'users.branch_id')->where('users.id', $log->user_id)->value('branches.country_id');
            }
            if ($country) {
                DB::table('audit_logs')->where('id', $log->id)->update(['country_id' => $country]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('share_refreshed_at'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('pin_set_at'));
    }
};
