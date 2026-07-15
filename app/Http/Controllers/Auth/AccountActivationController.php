<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\SecurityCodeService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountActivationController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('Auth/ActivateAccount', ['email' => $request->query('email', '')]);
    }

    public function store(Request $request, SecurityCodeService $codes)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $record = $codes->verify($data['email'], 'account_activation', $data['code'], true);
        $user = User::whereKey($record->user_id)->where('email', strtolower(trim($data['email'])))->firstOrFail();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
            'must_change_password' => false,
            'status' => 'active',
        ])->save();
        event(new PasswordReset($user));

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'post',
            'module' => 'seguridad',
            'description' => trim($user->name.' '.$user->last_name).' activo su cuenta con codigo de verificacion.',
            'old_values' => null,
            'new_values' => ['email' => $user->email],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return redirect()->route('login')->with('success', 'Cuenta activada. Ya puedes iniciar sesion.');
    }
}
