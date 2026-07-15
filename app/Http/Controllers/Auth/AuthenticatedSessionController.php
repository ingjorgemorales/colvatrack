<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class AuthenticatedSessionController extends Controller
{
    public function create(){ return Inertia::render('Auth/Login'); }
    public function store(Request $request)
    {
        $request->validate(['email'=>['required','email'],'password'=>['required']]);
        $key = 'login|'.$request->input('email').'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Intenta de nuevo en '.ceil($seconds / 60).' minutos.']);
        }
        if (!Auth::attempt($request->only('email','password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 1800);
            $attemptsLeft = RateLimiter::remaining($key, 3);
            $msg = 'Las credenciales no coinciden con nuestros registros.';
            if ($attemptsLeft > 0) {
                $msg .= ' Te quedan '.$attemptsLeft.' intento'.($attemptsLeft !== 1 ? 's' : '').'.';
            }
            throw ValidationException::withMessages(['email'=>$msg]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at'=>now()])->save();
        $this->audit($request, 'post', 'seguridad', trim($request->user()->name.' '.$request->user()->last_name).' inicio sesion.');
        return redirect()->intended(route('dashboard'));
    }
    public function destroy(Request $request){ $user = $request->user(); if ($user) { $this->audit($request, 'post', 'seguridad', trim($user->name.' '.$user->last_name).' cerro sesion.'); } Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
    private function audit(Request $request, string $action, string $module, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'old_values' => null,
            'new_values' => ['email' => $request->user()?->email],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
