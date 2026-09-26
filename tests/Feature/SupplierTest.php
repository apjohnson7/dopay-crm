<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_a_supplier_and_other_roles_cannot(): void
    {
        $this->seedReference();
        $ug = Country::where('iso2', 'UG')->first();
        $payload = ['company' => 'Nile Office Supplies', 'phone' => '+256 700 000 111', 'country_id' => $ug->id, 'tax_id' => '1012345678', 'payment_terms_days' => 30, 'bank_details' => 'Stanbic · 9030 1234 5678'];

        $this->actingAs($this->makeUser('Accounting Officer'))->get(route('suppliers.create'))->assertForbidden();
        $this->actingAs($this->makeUser('Accounting Officer'))->post(route('suppliers.store'), $payload)->assertForbidden();

        $admin = $this->makeUser('Super Administrator');
        $this->actingAs($admin)->get(route('suppliers.create'))->assertOk();
        $this->actingAs($admin)->post(route('suppliers.store'), $payload)->assertRedirect(route('suppliers.index'));

        $s = Supplier::firstOrFail();
        $this->assertSame('S-0001', $s->code);
        $this->assertSame('UGX', $s->currency_code);
        $this->assertSame('Stanbic · 9030 1234 5678', $s->bank_details);

        // Same TIN twice is refused
        $this->actingAs($admin)->post(route('suppliers.store'), ['company' => 'Other Name'] + $payload)->assertSessionHasErrors('tax_id');
    }
}
