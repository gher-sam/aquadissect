<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DelayedCloneWrite;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function approve(User $user, DelayedCloneWrite $writes): JsonResponse
    {
        abort_unless($user->role === User::ROLE_INSTRUCTOR, 422, 'Only instructor requests require approval.');

        return $writes->transaction(function () use ($user): JsonResponse {
            $user->update(['status' => User::STATUS_ACTIVE]);

            return response()->json(['user' => $user->portalProfile()]);
        });
    }
}
