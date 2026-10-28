<?php

namespace App\Http\Middleware;

use App\Services\DelayedCloneWrite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class CompletePendingSignOut
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->cookie('aquadissect_logout_pending') !== '1') {
            return $next($request);
        }

        app(DelayedCloneWrite::class)->transaction(function () use ($request): void {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        });

        $response = $request->is('api/*')
            ? response()->json(['message' => 'Signed out.', 'signedOut' => true], $request->is('api/logout') ? 200 : 401)
            : redirect()->route('login');

        return $response->header('Cache-Control', 'no-store')->withCookie(
            Cookie::forget('aquadissect_logout_pending', $request->getBaseUrl().'/')
        );
    }
}
