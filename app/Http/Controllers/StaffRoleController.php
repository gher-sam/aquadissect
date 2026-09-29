<?php

namespace App\Http\Controllers;

use App\Models\{User, RoleChangeEvent};
use App\Services\DelayedCloneWrite;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffRoleController extends Controller
{
    public function index()
    {
        return response()->json([
            'staff' => User::whereIn('role', ['instructor', 'program_head'])->where('status', 'active')->orderBy('id')->get()->map->portalProfile(),
            'history' => RoleChangeEvent::latest('id')->limit(50)->get(['user_id', 'actor_id', 'previous_role', 'new_role', 'created_at']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, User $user, DelayedCloneWrite $writes)
    {
        $data = $request->validate(['role' => ['required', Rule::in(['instructor', 'program_head'])], 'confirmed' => ['required', 'accepted']]);
        abort_if($user->id === $request->user()->id, 403);
        return $writes->transaction(function () use ($user, $request, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->isActive() && in_array($user->role, ['instructor', 'program_head'], true), 422);
            if ($user->role !== $data['role']) {
                RoleChangeEvent::create(['user_id' => $user->id, 'actor_id' => $request->user()->id, 'previous_role' => $user->role, 'new_role' => $data['role']]);
                $user->update(['role' => $data['role']]);
            }
            return response()->json(['user' => $user->portalProfile()]);
        });
    }
}
