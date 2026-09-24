<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return $next($request);

        $version = (int) $user->auth_session_version;
        $sessionVersion = $request->session()->get('auth_session_version');
        if (! $user->is_active || ($sessionVersion === null ? $version !== 0 : (int) $sessionVersion !== $version)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'انتهت جلسة الدخول أو تم إيقاف الحساب.']);
        }

        if ($sessionVersion === null) $request->session()->put('auth_session_version', $version);
        return $next($request);
    }
}
