<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Scope;
use Illuminate\Http\Request;

/**
 * A country's own account: the company details printed on its documents, and who works in it.
 * Country Administrators manage their own account; the Super Administrator can open any of them.
 */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $country = Country::findOrFail($request->query('country', Scope::countryId() ?? $user->countryId()));
        $this->ensureCountry($country->id);

        return view('account.edit', [
            'country' => $country,
            'countries' => $user->isGlobal() ? Country::where('is_active', true)->orderBy('name')->get() : collect([$country]),
            'users' => User::with('branch', 'roles')->whereHas('branch', fn ($b) => $b->where('country_id', $country->id))
                ->when(! $user->isGlobal(), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', config('dopay.global_roles'))))
                ->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Country $country)
    {
        $this->authorize('settings.manage');
        $this->ensureCountry($country->id);
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'bank_details' => ['nullable', 'string', 'max:500'],
            'mobile_money' => ['nullable', 'string', 'max:190'],
        ]);
        $before = $country->only(array_keys($data));
        $country->update($data);
        AuditLogger::log('Updated account details', $country, $before, $country->only(array_keys($data)), 'DoPay '.$country->name);

        return redirect()->route('account.edit', ['country' => $country->id])->with('status', 'Account details saved. New documents use them straight away.');
    }
}
