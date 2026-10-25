<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\AccountActivationToken;
use App\Models\RosterEvent;
use App\Models\User;
use App\Services\DelayedCloneWrite;
use App\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        PortalData $portalData,
        DelayedCloneWrite $writes,
    ): JsonResponse {
        return $writes->transaction(function () use ($request, $portalData): JsonResponse {
            if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
                throw ValidationException::withMessages(['identifier' => 'The student ID, email or password is incorrect.']);
            }

            $request->session()->regenerate();
            $user = $request->user();

            if ($user->role === User::ROLE_STUDENT) {
                $request->session()->put('student_password_revision', $user->password_revision);
                if ($user->must_change_password) {
                    return response()->json($portalData->for($user))->header('Cache-Control', 'no-store');
                }
            }

            if (! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages(['identifier' => $user->role === User::ROLE_STUDENT
                    ? 'This student account needs activation. Use the code supplied by the administrator.'
                    : 'This account is waiting for administrator approval.']);
            }

            return response()->json($portalData->for($user));
        });
    }

    public function register(RegisterRequest $request, DelayedCloneWrite $writes): JsonResponse
    {
        if (config('services.google.enabled')) {
            throw ValidationException::withMessages(['email' => 'Connect your Google account to request instructor access.']);
        }

        return $writes->transaction(function () use ($request): JsonResponse {
            $user = User::query()->create($request->userAttributes());

            return response()->json([
                'message' => 'Instructor request submitted for administrator approval.',
            ], 201);
        });
    }

    public function activate(Request $request, DelayedCloneWrite $writes): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:48'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        return $writes->transaction(function () use ($validated): JsonResponse {
            $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->first();
            $token = $user ? AccountActivationToken::query()->where('user_id', $user->id)->lockForUpdate()->first() : null;
            if (! $user || $user->role !== User::ROLE_STUDENT || $user->status !== User::STATUS_PENDING
                || ! $token || $token->expires_at->isPast()
                || ! hash_equals($token->token_hash, hash('sha256', $validated['code']))) {
                throw ValidationException::withMessages(['code' => 'This activation code is invalid or expired. Ask the administrator for a new code.']);
            }

            $user->update(['password' => $validated['password'], 'status' => User::STATUS_ACTIVE]);
            $token->delete();
            RosterEvent::query()->create(['user_id' => $user->id, 'action' => 'student_activated']);

            return response()->json(['message' => 'Account activated. You can now sign in.']);
        });
    }

    public function logout(Request $request, DelayedCloneWrite $writes): JsonResponse
    {
        return $writes->transaction(function () use ($request): JsonResponse {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Signed out.']);
        });
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->portalProfile()]);
    }
}
