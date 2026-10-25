<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureStudentPasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || $user->role !== User::ROLE_STUDENT) {
            return $next($request);
        }
        $revision = $request->session()->get('student_password_revision');
        if ($revision === null && $user->password_revision === 0 && ! $user->must_change_password) {
            $request->session()->put('student_password_revision', 0);
        } elseif ($revision !== $user->password_revision) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->is('api/*') ? response()->json(['message' => 'Your password access changed. Sign in again.'], 401)->header('Cache-Control', 'no-store')
                : redirect()->route('login')->header('Cache-Control', 'no-store');
        }
        if ($user->must_change_password && ! $request->is('change-password', 'api/change-password', 'api/logout', 'sign-out')) {
            return $request->is('api/*') ? response()->json(['message' => 'Choose a new password before opening your course.',
                'passwordChangeRequired' => true, 'passwordChangeUrl' => route('password.change')], 403)->header('Cache-Control', 'no-store')
                : redirect()->route('password.change')->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
