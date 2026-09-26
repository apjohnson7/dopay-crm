<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function security(Request $request)
    {
        return view('profile.security', ['user' => $request->user()]);
    }

    public function pin(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'pin' => ['required', 'digits:6', 'confirmed', 'not_in:000000,111111,123456,654321,121212,112233,999999,246810'],
        ]);
        $request->user()->setSigningPin($data['pin']);

        return back()->with('status', 'Signing PIN saved.');
    }
}
