<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_country_sees_only_its_own_account_and_the_super_admin_sees_all(): void
    {
        $this->seedReference();
        $ug = Country::where('iso2', 'UG')->first();
        $hq = Branch::where('code', 'HQ')->first();
        $customer = Customer::create(['code' => 'C-UG-0001', 'name' => 'Kampala Buyer', 'country_id' => $ug->id, 'branch_id' => $hq->id, 'currency_code' => 'UGX']);

        // A Nigerian Finance Manager (formerly cross-country) is now limited to the Nigeria account.
        $ngManager = $this->makeUser('Finance Manager', 'IK');
        $this->actingAs($ngManager)->get(route('customers.show', $customer))->assertForbidden();
        $this->actingAs($ngManager)->get(route('customers.index'))->assertOk()->assertDontSee('Kampala Buyer');

        // A CFO based in Uganda signs group forms but does not browse Nigeria.
        $this->assertFalse($this->makeUser('CFO', 'HQ')->canActForCountry(Country::where('iso2', 'NG')->value('id')));

        // The Super Administrator opens every account.
        $admin = $this->makeUser('Super Administrator', 'HQ');
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($admin)->post(route('scope'), ['country_id' => Country::where('iso2', 'NG')->value('id')]);
        $this->actingAs($admin)->get(route('customers.index'))->assertDontSee('Kampala Buyer');
    }
}
