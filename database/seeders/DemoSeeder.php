<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MessagingService;
use App\Services\PaymentService;
use App\Services\TaxService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample people and records matching the prototype, for training and demos.
 * Every demo user signs in with DOPAY_DEMO_PASSWORD and signs documents with PIN 2468.
 */
class DemoSeeder extends Seeder
{
    public const PIN = '2468';

    public function run(): void
    {
        $password = Hash::make(env('DOPAY_DEMO_PASSWORD'));
        $b = fn (string $code) => Branch::where('code', $code)->firstOrFail();
        $cc = fn (string $iso) => Country::where('iso2', $iso)->firstOrFail();

        $people = [
            ['Grace Nakato', 'grace.nakato', 'Super Administrator', 'HQ'],
            ['Patrick Mugisha', 'patrick.mugisha', 'Finance Manager', 'HQ'],
            ['David Ssemanda', 'david.ssemanda', 'Accounting Officer', 'HQ'],
            ['Moses Okello', 'moses.okello', 'Branch Manager', 'EBB'],
            ['Brian Tumusiime', 'brian.tumusiime', 'Country Manager', 'HQ'],
            ['Ruth Achieng', 'ruth.achieng', 'Assistant Manager', 'HQ'],
            ['Samuel Kato', 'samuel.kato', 'Sales/Customer Officer', 'HQ'],
            ['Esther Nambi', 'esther.nambi', 'Document Officer', 'HQ'],
            ['Ngozi Eze', 'ngozi.eze', 'Accountant', 'IK'],
            ['Chinedu Obi', 'chinedu.obi', 'Country Manager', 'IK'],
            ['Helen Mensah', 'helen.mensah', 'Financial Controller', 'HQ'],
            ['Joseph Waiswa', 'joseph.waiswa', 'Auditor', 'HQ'],
            ['Jean-Paul Fotso', 'jeanpaul.fotso', 'Accountant', 'DLA'],
            ['Awa Kone', 'awa.kone', 'Accountant', 'ABJ'],
            ['Peter Lwanga', 'peter.lwanga', 'CFO', 'HQ'],
            ['Sarah Ouma', 'sarah.ouma', 'CEO', 'HQ'],
            ['Daniel Asante', 'daniel.asante', 'Regional Manager', 'HQ'],
            ['Emmanuel Tabi', 'emmanuel.tabi', 'Country Manager', 'DLA'],
            ['Irene Atuhaire', 'irene.atuhaire', 'Country Administrator', 'HQ'],
            ['Tunde Bakare', 'tunde.bakare', 'Country Administrator', 'IK'],
        ];
        $users = [];
        foreach ($people as [$name, $handle, $role, $branch]) {
            $u = User::updateOrCreate(['email' => $handle.'@dopay.example'], [
                'name' => $name, 'branch_id' => $b($branch)->id, 'job_title' => $role, 'password' => $password, 'is_active' => true,
            ]);
            $u->forceFill(['email_verified_at' => now()])->save();
            $u->setSigningPin(self::PIN);
            $u->syncRoles([$role]);
            $users[$handle] = $u;
        }
        $b('HQ')->update(['manager_id' => $users['patrick.mugisha']->id]);
        $b('IK')->update(['manager_id' => $users['chinedu.obi']->id]);
        $b('DLA')->update(['manager_id' => $users['emmanuel.tabi']->id]);

        $cat = fn (string $n) => ProductCategory::where('name', $n)->value('id');
        $products = [
            ['P-AGR-001', 'Organic fertiliser 50kg', 'Agriculture', 'Bag', 180000, 120000],
            ['P-AGR-002', 'Maize seed 10kg', 'Agriculture', 'Bag', 95000, 60000],
            ['P-HLT-001', 'Herbal Immune Booster', 'Health', 'Bottle', 45000, 22000],
            ['P-HSE-001', 'Liquid soap 5L', 'Household', 'Jerrycan', 38000, 21000],
            ['P-HSE-002', 'Solar lantern', 'Household', 'Unit', 65000, 41000],
            ['S-SVC-001', 'Farmer training (per day)', 'Services', 'Day', 350000, 0],
            ['S-SVC-002', 'Distributor onboarding', 'Services', 'Package', 1200000, 0],
        ];
        $countryIds = Country::pluck('id')->all();
        foreach ($products as [$sku, $name, $c, $unit, $price, $cost]) {
            $p = Product::updateOrCreate(['sku' => $sku], ['name' => $name, 'product_category_id' => $cat($c), 'unit' => $unit, 'price' => $price, 'cost' => $cost, 'currency_code' => 'UGX', 'taxable' => true, 'status' => 'active']);
            $p->countries()->sync($countryIds);
        }

        $customers = [
            ['C-UG-0001', 'John Mukasa', 'John Uganda Ltd', 'HQ', '1002345678', 'Distributor', 30],
            ['C-UG-0002', 'Alice Namara', 'ABC Ltd', 'HQ', '1003456782', 'Retail', 30],
            ['C-UG-0003', 'Robert Wafula', 'Mbale Agro Supplies', 'HQ', '1004567890', 'Cooperative', 45],
            ['C-UG-0004', 'Kato Ivan', 'Kato Household Stores', 'EBB', '1005678904', 'Retail', 30],
            ['C-NG-0001', 'Adaeze Nwosu', 'Ikeja Wellness Pharmacy', 'IK', '23456789-0001', 'Health partner', 30],
            ['C-CM-0001', 'Marie Ngono', 'Douala Agri Coop', 'DLA', 'M012345678901A', 'Cooperative', 30],
        ];
        $cust = [];
        foreach ($customers as [$code, $name, $company, $branch, $tin, $category, $terms]) {
            $br = $b($branch);
            $cust[$code] = Customer::updateOrCreate(['code' => $code], [
                'type' => 'business', 'name' => $name, 'company' => $company, 'country_id' => $br->country_id, 'branch_id' => $br->id,
                'tax_id' => $tin, 'category' => $category, 'payment_terms_days' => $terms, 'currency_code' => $br->country->currency_code,
                'phone' => '+000 000 000', 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'account_manager_id' => $users['samuel.kato']->id,
            ]);
        }

        $suppliers = [
            ['S-0001', 'Kampala Print House', 'Ivan Lubega', 'UG', '1009988776', 'Printing, stationery', 'Centenary Bank · Kampala Print House · 3100000111', 30],
            ['S-0002', 'Victoria Logistics', 'Ruth Atim', 'UG', '1008877665', 'Courier, freight', 'Stanbic · Victoria Logistics · 9030000222', 15],
            ['S-0003', 'Lagos Courier Express', 'Bola Ade', 'NG', '22334455-0001', 'Courier', 'GTBank · Lagos Courier Express · 0123456789', 30],
        ];
        foreach ($suppliers as [$code, $company, $contact, $iso, $tin, $supplies, $bank, $terms]) {
            $country = $cc($iso);
            \App\Models\Supplier::updateOrCreate(['code' => $code], ['company' => $company, 'contact_person' => $contact, 'country_id' => $country->id, 'currency_code' => $country->currency_code,
                'tax_id' => $tin, 'supplies' => $supplies, 'bank_details' => $bank, 'payment_terms_days' => $terms, 'phone' => '+000 000 000']);
        }
        app(\App\Services\NumberingService::class)->next('SUPPLIER');
        \App\Models\NumberSequence::where('scope', 'SUPPLIER')->update(['last_value' => count($suppliers)]);

        if (\App\Models\Invoice::count() === 0) {
            $this->invoices($users, $cust);
            $this->taxes($users, $b, $cc);
        }

        $messaging = app(MessagingService::class);
        if (\App\Models\Message::count() === 0) {
            $d = $users['david.ssemanda'];
            $messaging->send(Conversation::directBetween($users['ngozi.eze'], $d), $users['ngozi.eze'], 'Morning David. Can you confirm the Lagos courier memo was paid from Kampala?');
            $messaging->send(Conversation::directBetween($users['patrick.mugisha'], $d), $users['patrick.mugisha'], 'David, this cash advance needs receipts by Friday.');
        }
    }

    private function invoices(array $u, array $c): void
    {
        $svc = app(InvoiceService::class);
        $pay = app(PaymentService::class);
        $line = fn (string $sku, float $qty, float $disc = 0) => (function () use ($sku, $qty, $disc) {
            $p = Product::where('sku', $sku)->first();

            return ['product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty, 'unit_price' => $p->price, 'discount_pct' => $disc];
        })();

        $plan = [
            // customer, issue date, lines, final state, payment
            ['C-UG-0001', '2026-07-10', [$line('S-SVC-002', 2), $line('P-AGR-001', 10)], 'sent', 3000000],
            ['C-UG-0002', '2026-08-05', [$line('S-SVC-001', 4), $line('P-HLT-001', 20)], 'sent', null],
            ['C-UG-0004', '2026-08-20', [$line('P-HSE-001', 30), $line('P-HSE-002', 50, 5)], 'sent', 1500000],
            ['C-UG-0003', '2026-09-08', [$line('P-AGR-001', 25), $line('P-AGR-002', 20)], 'approved', null],
            ['C-UG-0001', '2026-09-22', [$line('P-HSE-002', 40)], 'pending_approval', null],
        ];
        foreach ($plan as [$code, $date, $items, $state, $paid]) {
            $inv = $svc->saveDraft(['customer_id' => $c[$code]->id, 'issue_date' => $date, 'items' => $items], $u['david.ssemanda']);
            if ($state === 'draft') {
                continue;
            }
            $svc->submit($inv);
            if ($state === 'pending_approval') {
                continue;
            }
            $svc->approve($inv->fresh(), $u['patrick.mugisha']);
            $svc->generate($inv->fresh());
            if ($state === 'sent') {
                $svc->markSent($inv->fresh());
            }
            if ($paid) {
                $pay->record($c[$code], ['amount' => $paid, 'method' => 'Bank transfer', 'reference' => 'DEMO-'.$inv->id, 'paid_on' => now()->parse($date)->addDays(20)->toDateString()], [$inv->id => $paid], $u['david.ssemanda']);
            }
        }
    }

    /** Tax payments so the Taxes page shows paid, overdue and pending returns. */
    private function taxes(array $u, \Closure $b, \Closure $cc): void
    {
        $tax = app(TaxService::class);
        $ug = $cc('UG');
        $periods = $tax->periods($ug)->keyBy('period');
        $rows = [
            [$ug, 'HQ', '2026-08-14', 'VAT return, July 2026', $periods['2026-07']['vat'] ?? 0, '2026-07', 'paid'],
            [$ug, 'HQ', '2026-09-14', 'VAT return, August 2026', $periods['2026-08']['vat'] ?? 0, '2026-08', 'paid'],
            [$ug, 'HQ', '2026-09-14', 'PAYE, August 2026', 950000, null, 'paid'],
        ];
        foreach ($rows as [$country, $branch, $on, $desc, $amount, $period, $status]) {
            if ($amount <= 0) {
                continue;
            }
            $e = $tax->record($country, $b($branch), ['description' => $desc, 'amount' => $amount, 'spent_on' => $on, 'tax_period' => $period, 'payment_method' => 'Bank transfer'], $u['david.ssemanda']);
            if ($status === 'paid') {
                $tax->markPaid($e, $u['patrick.mugisha']);
            }
        }
    }
}
