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
            'pin' => 'required|digits_between:4,6|confirmed',
        ]);
        $request->user()->setSigningPin($data['pin']);

        return back()->with('status', 'Signing PIN saved.');
    }
}
