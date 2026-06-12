<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $email = $request->input('email');
        $attemptKey = 'password_reset_attempts|'.$email;
        $blockKey = 'password_reset_block|'.$email;
        $blockNumberKey = 'password_reset_block_number|'.$email;
        $attempts = (int) Cache::get($attemptKey, 0);
        $blockedUntil = Cache::get($blockKey);

        if ($blockedUntil && now()->lessThan($blockedUntil)) {
            $minutes = ceil(now()->diffInMinutes($blockedUntil));
            return back()->withErrors(['email' => 'Has superado el limite de intentos. Intenta de nuevo en '.$minutes.' minutos.']);
        }

        if ($attempts >= 3) {
            $blockNumber = (int) Cache::get($blockNumberKey, 0);
            $waitMinutes = 30 * (1 << $blockNumber);
            Cache::put($blockKey, now()->addMinutes($waitMinutes), now()->addMinutes($waitMinutes + 1));
            Cache::put($blockNumberKey, $blockNumber + 1, now()->addDays(1));
        }

        $status = Password::sendResetLink($request->only('email'));
        if ($status === Password::RESET_LINK_SENT) {
            Cache::put($attemptKey, $attempts + 1, now()->addDays(1));
            if ($attempts >= 3) {
                $minutes = ceil(now()->diffInMinutes(Cache::get($blockKey)));
                return back()->with('success', 'Te hemos enviado un enlace de recuperacion a tu correo. Podras solicitar otro en '.$minutes.' minutos.');
            }
            return back()->with('success', 'Te hemos enviado un enlace de recuperacion a tu correo.');
        }

        if ($attempts >= 3) {
            Cache::forget($blockKey);
            Cache::forget($blockNumberKey);
        }
        return back()->withErrors(['email' => 'No encontramos una cuenta con ese correo.']);
    }
}
