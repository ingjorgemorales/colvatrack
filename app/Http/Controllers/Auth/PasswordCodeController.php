<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SecurityCodeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PasswordCodeController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('Auth/VerifyPasswordCode', [
            'email' => $request->query('email', ''),
            'expiresIn' => 120,
        ]);
    }

    public function store(Request $request, SecurityCodeService $codes)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $codes->verify($data['email'], 'password_reset', $data['code'], true);
        $request->session()->put('password_reset_email', strtolower(trim($data['email'])));
        $request->session()->put('password_reset_verified_until', now()->addMinutes(10));

        return redirect()->route('password.reset')->with('success', 'Codigo verificado. Crea tu nueva contrasena.');
    }
}
