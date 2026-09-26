<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Services\BudgetMonitor;
use App\Support\Scope;
use Illuminate\Http\Request;

class BudgetMonitorController extends Controller
{
    public function __invoke(Request $request, BudgetMonitor $monitor)
    {
        $this->authorize('reports.view');
        $country = Country::findOrFail($request->query('country', Scope::countryId() ?? $request->user()->countryId()));
        $this->ensureCountry($country->id);
        $month = (int) $request->query('month', now()->month);
        $ytd = $request->query('mode') === 'ytd';

        return view('budget.index', [
            'country' => $country, 'month' => $month, 'ytd' => $ytd, 'year' => (int) $request->query('year', now()->year),
            'report' => $monitor->report($country, (int) $request->query('year', now()->year), $month, $ytd),
            'countries' => $request->user()->isGlobal() ? Country::orderBy('name')->get() : collect([$country]),
        ]);
    }
}
