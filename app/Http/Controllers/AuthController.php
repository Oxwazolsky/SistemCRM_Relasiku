<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Password};
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginForm() { return view('auth.login'); }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials + ['active' => true], $request->boolean('remember'))) throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function forgotForm() { return view('auth.forgot-password'); }
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);
        return back()->with('status', 'Jika akun tersedia, tautan pemulihan telah dikirim.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            abort_unless($user->active, 403);
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        return $status === Password::PASSWORD_RESET ? redirect()->route('login')->with('status', __($status)) : back()->withErrors(['email' => __($status)]);
    }
}
