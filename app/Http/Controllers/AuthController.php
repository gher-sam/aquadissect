<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\User;
use App\Services\DelayedCloneWrite;
use App\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
            }

            $request->session()->regenerate();
            $user = $request->user();

            if (! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages(['email' => 'This account is waiting for administrator approval.']);
            }

            return response()->json($portalData->for($user));
        });
    }

    public function register(RegisterRequest $request, DelayedCloneWrite $writes): JsonResponse
    {
        return $writes->transaction(function () use ($request): JsonResponse {
            $user = User::query()->create($request->userAttributes());

            if ($user->role === User::ROLE_STUDENT) {
                $class = LearningClass::query()->where('code', $user->class_code)->where('status', 'active')->firstOrFail();
                ClassEnrollment::query()->create([
                    'class_id' => $class->id,
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            }

            return response()->json([
                'message' => $user->isActive()
                    ? 'Student account created. You can now sign in.'
                    : 'Instructor request submitted for administrator approval.',
            ], 201);
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
