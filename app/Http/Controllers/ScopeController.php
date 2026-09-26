<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ScopeController extends Controller
{
    public function update(Request $request)
    {
        $id = $request->input('country_id');
        if ($request->user()->isGlobal()) {
            $id ? session(['scope_country' => (int) $id]) : session()->forget('scope_country');
        }

        return back();
    }
}
