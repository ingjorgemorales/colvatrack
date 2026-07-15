<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

class NewPasswordController extends Controller
{
    public function create(Request $request)
    {
        if (! $this->hasValidVerifiedSession($request)) {
            return redirect()->route('password.request')->withErrors(['email' => 'Primero debes verificar el codigo enviado a tu correo.']);
        }

        return Inertia::render('Auth/ResetPassword', ['email' => $request->session()->get('password_reset_email')]);
    }

    public function store(Request $request)
    {
        if (! $this->hasValidVerifiedSession($request)) {
            return redirect()->route('password.request')->withErrors(['email' => 'La verificacion vencio. Solicita un codigo nuevo.']);
        }

        $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $email = $request->session()->get('password_reset_email');
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['password' => Hash::make($request->password), 'remember_token' => Str::random(60), 'must_change_password' => false])->save();
        event(new PasswordReset($user));
        $request->session()->forget(['password_reset_email', 'password_reset_verified_until']);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'post',
            'module' => 'seguridad',
            'description' => trim($user->name.' '.$user->last_name).' restablecio su contrasena con codigo de verificacion.',
            'old_values' => null,
            'new_values' => ['email' => $user->email],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return redirect()->route('login')->with('success', 'Contrasena actualizada. Ya puedes iniciar sesion.');
    }

    private function hasValidVerifiedSession(Request $request): bool
    {
        $email = $request->session()->get('password_reset_email');
        $until = $request->session()->get('password_reset_verified_until');

        return $email && $until && now()->lessThan($until);
    }
}
