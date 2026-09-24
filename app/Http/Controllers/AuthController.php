<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View { return view('auth.login'); }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email'=>'required|email','password'=>'required|string']);
        if (! Auth::attempt(array_merge($credentials,['is_active'=>true]), $request->boolean('remember'))) {
            return back()->withErrors(['email'=>'بيانات الدخول غير صحيحة أو المستخدم غير نشط.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        $request->session()->put('auth_session_version', (int) $user?->auth_session_version);
        $user?->loadMissing('role');

        if (! $user?->role) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'الحساب غير مرتبط بدور صلاحيات. شغّل أمر إنشاء المدير مرة أخرى بعد تنفيذ migrate --seed.',
            ])->onlyInput('email');
        }

        if (! $user->hasPermission('dashboard.view')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'الحساب لا يملك صلاحية الدخول للوحة الرئيسية. راجع الدور والصلاحيات من إدارة المستخدمين.',
            ])->onlyInput('email');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
