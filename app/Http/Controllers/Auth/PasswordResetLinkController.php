<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Services\SecurityCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request, SecurityCodeService $codes)
    {
        $request->validate(['email' => ['required', 'email']]);
        $email = strtolower(trim($request->input('email')));
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

        $user = User::where('email', $email)->first();
        if ($user) {
            $code = $codes->issue($user, $email, 'password_reset', 2);
            Mail::to($user->email)->send(new PasswordResetCodeMail($user->name, $code, 2));
            Cache::put($attemptKey, $attempts + 1, now()->addDays(1));

            if ($attempts >= 3) {
                $minutes = ceil(now()->diffInMinutes(Cache::get($blockKey)));
                return redirect()->route('password.code', ['email' => $email])
                    ->with('success', 'Te enviamos un codigo de recuperacion. Podras solicitar otro en '.$minutes.' minutos.');
            }
        }

        return redirect()->route('password.code', ['email' => $email])
            ->with('success', 'Si el correo existe, enviamos un codigo de recuperacion.');
    }
}
