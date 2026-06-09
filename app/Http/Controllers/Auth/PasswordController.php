<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
class PasswordController extends Controller
{
    public function change(){ return Inertia::render('Auth/ChangePassword'); }
    public function update(Request $request){ $data=$request->validate(['password'=>['required','confirmed',Password::min(8)->mixedCase()->numbers()->symbols()]]); $request->user()->update(['password'=>Hash::make($data['password']),'must_change_password'=>false]); return redirect()->route('dashboard')->with('success','Contrasena actualizada.'); }
}
