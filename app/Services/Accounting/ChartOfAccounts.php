<?php

namespace App\Services\Accounting;

use App\Models\Country;
use App\Models\LedgerAccount;
use Illuminate\Validation\ValidationException;

class ChartOfAccounts
{
    /** Creates the country's chart from its template; safe to run again (existing codes are kept). */
    public function install(Country $country): void
    {
        $template = config('accounting.chart_by_country.'.$country->iso2, 'ifrs');
        foreach (config('accounting.templates.'.$template) as [$code, $name, $type, $role, $line]) {
            LedgerAccount::firstOrCreate(['country_id' => $country->id, 'code' => $code], [
                'name' => $name, 'type' => $type, 'role' => $role, 'statement_line' => $line, 'is_system' => true, 'is_active' => true,
            ]);
        }
    }

    public function template(Country $country): string
    {
        return config('accounting.chart_by_country.'.$country->iso2, 'ifrs');
    }

    /** The account playing a posting role in this country, e.g. 'ar' or 'vat'. */
    public function account(int $countryId, string $role): LedgerAccount
    {
        $account = LedgerAccount::where('country_id', $countryId)->where('role', $role)->first();
        if (! $account) {
            $country = Country::findOrFail($countryId);
            $this->install($country);
            $account = LedgerAccount::where('country_id', $countryId)->where('role', $role)->first()
                ?? throw ValidationException::withMessages(['ledger' => "The {$country->name} chart has no account for '{$role}'."]);
        }

        return $account;
    }
}
