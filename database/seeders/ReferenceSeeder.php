<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DocumentTemplate;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\ProductCategory;
use App\Models\ReminderRule;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['USD', 'US Dollar', 2], ['UGX', 'Ugandan Shilling', 0], ['NGN', 'Nigerian Naira', 2], ['XAF', 'Central African CFA franc', 0], ['XOF', 'West African CFA franc', 0]] as [$code, $name, $dec]) {
            Currency::updateOrCreate(['code' => $code], ['name' => $name, 'decimals' => $dec]);
        }

        // Units per 1 USD. Update them in Settings as rates move; each document keeps the rate of its date.
        foreach (['USD' => 1, 'UGX' => 3700, 'NGN' => 1530, 'XAF' => 600, 'XOF' => 600] as $code => $rate) {
            ExchangeRate::updateOrCreate(['currency_code' => $code, 'effective_on' => '2026-01-01'], ['rate_per_usd' => $rate]);
        }

        $countries = [
            ['UG', 'Uganda', 'UGD', 'DoPay Uganda Ltd', 'UGX', 'VAT', 18, 15, 'Africa/Kampala', 'EAT', [['HQ', 'Kampala', 'Head office, Kampala Road', 'Finance'], ['EBB', 'Entebbe', 'Entebbe Road office', 'Sales']]],
            ['NG', 'Nigeria', 'NG', 'DoPay Nigeria Co. Ltd', 'NGN', 'VAT', 7.5, 21, 'Africa/Lagos', 'WAT', [['IK', 'Lagos', 'Ikeja office', 'Sales']]],
            ['CM', 'Cameroon', 'CMR', 'DoPay Cameroon Co., Ltd.', 'XAF', 'TVA', 19.25, 15, 'Africa/Douala', 'WAT', [['DLA', 'Douala', 'Akwa office', 'Sales']]],
            ['CI', 'Ivory Coast', 'CIV', 'DoPay Ivory Coast Co., Ltd.', 'XOF', 'TVA', 18, 15, 'Africa/Abidjan', 'GMT', [['ABJ', 'Abidjan', 'Plateau office', 'Sales']]],
        ];
        $profiles = [
            'UG' => ['Plot 00, Kampala Road, Kampala', '+256 700 000 000', 'accounts.ug@dopay.example', '1000000000', 'Stanbic Bank Uganda · A/C 9030 0000 00000', 'MTN MoMo merchant 000000'],
            'NG' => ['Plot 5, Allen Avenue, Ikeja, Lagos', '+234 800 000 0000', 'accounts.ng@dopay.example', '12345678-0001', 'GTBank · A/C 0000 0000 00', null],
            'CM' => ['Rue Joss, Akwa, Douala', '+237 600 000 000', 'accounts.cm@dopay.example', 'M000000000000A', 'Afriland First Bank · A/C 0000 0000 000', 'MTN MoMo 000000'],
            'CI' => ['Plateau, Abidjan', '+225 07 00 00 00 00', 'accounts.ci@dopay.example', 'CI-0000000-A', 'SGCI · A/C 0000 0000 000', 'Orange Money 000000'],
        ];
        foreach ($countries as [$iso, $name, $doc, $entity, $cur, $taxName, $rate, $filing, $tz, $tzl, $branches]) {
            [$addr, $phone, $email, $tin, $bank, $momo] = $profiles[$iso];
            $c = Country::firstOrNew(['iso2' => $iso]);
            if (! $c->exists) {
                $c->fill(['address' => $addr, 'phone' => $phone, 'email' => $email, 'tax_id' => $tin, 'bank_details' => $bank, 'mobile_money' => $momo]);
            }
            $c->fill([
                'name' => $name, 'doc_code' => $doc, 'legal_entity' => $entity, 'currency_code' => $cur,
                'tax_name' => $taxName, 'tax_rate' => $rate, 'vat_filing_day' => $filing, 'timezone' => $tz, 'timezone_label' => $tzl, 'is_active' => true,
            ])->save();
            foreach ($branches as [$code, $bname, $office, $dept]) {
                Branch::updateOrCreate(['country_id' => $c->id, 'code' => $code], ['name' => $bname, 'office' => $office, 'department' => $dept, 'is_active' => true]);
            }
        }

        foreach (Country::all() as $c) {
            app(\App\Services\Accounting\ChartOfAccounts::class)->install($c); // IFRS-style or SYSCOHADA chart per country
        }

        foreach (config('dopay.expense_categories') as $i => [$name, $group, $note]) {
            ExpenseCategory::updateOrCreate(['name' => $name], ['group' => $group, 'position' => $i + 1, 'note' => $note]);
        }
        foreach (['Agriculture', 'Health', 'Household', 'Services'] as $name) {
            ProductCategory::firstOrCreate(['name' => $name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(config('dopay.permissions')) as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        foreach (config('dopay.roles') as $role => $perms) {
            Role::findOrCreate($role, 'web')->syncPermissions($perms === ['*'] ? Permission::all() : $perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $templates = [
            ['Invoice message', 'invoice', "Hello {{customer_name}}, your invoice {{invoice_number}} for {{total}} is ready. Due {{due_date}}. View and pay: {{link}}\n{{company_name}}"],
            ['Payment reminder', 'reminder', "Hello {{customer_name}}, a friendly reminder that invoice {{invoice_number}} ({{balance}}) is due on {{due_date}}. Pay here: {{link}}"],
            ['Overdue notice', 'reminder', "Hello {{customer_name}}, invoice {{invoice_number}} is now overdue with {{balance}} outstanding. Please pay or contact us: {{link}}"],
            ['Receipt message', 'receipt', "Thank you {{customer_name}}. We received {{amount}} on {{date}}. Receipt {{receipt_number}}: {{link}}"],
            ['Customer statement', 'statement', "Hello {{customer_name}}, your statement to {{date}} shows a balance of {{balance}}. Details: {{link}}"],
        ];
        foreach ($templates as [$name, $type, $body]) {
            DocumentTemplate::firstOrCreate(['name' => $name], ['type' => $type, 'body' => $body]);
        }
        $reminderTpl = DocumentTemplate::where('name', 'Payment reminder')->value('id');
        foreach (config('dopay.reminders') as $r) {
            ReminderRule::firstOrCreate(['label' => $r['label']], $r + ['document_template_id' => $reminderTpl, 'is_active' => true]);
        }

        Setting::updateOrCreate(['key' => 'organization'], ['value' => ['name' => 'Dopay CRM', 'email' => 'finance@dopay.example', 'phone' => '', 'address' => '']]);
    }
}
