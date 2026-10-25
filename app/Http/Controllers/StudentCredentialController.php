<?php

namespace App\Http\Controllers;

use App\Models\RosterEvent;
use App\Models\User;
use App\Services\StudentPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentCredentialController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        if (! $request->user()->must_change_password) {
            return redirect()->route('portal.home');
        }

        return response()->view('change-password')->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, StudentPasswords $passwords)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $user = $passwords->change($request->user(), (int) $request->session()->get('student_password_revision', -1), $data['password']);
        Auth::setUser($user);
        $request->session()->regenerate();
        $request->session()->put('student_password_revision', $user->password_revision);

        return $request->expectsJson() ? response()->json(['message' => 'Your new password is saved.', 'redirect' => route('portal.home')])->header('Cache-Control', 'no-store')
            : redirect()->route('portal.home', [], 303);
    }

    public function signOut(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', [], 303);
    }

    public function reset(Request $request, User $user, StudentPasswords $passwords)
    {
        $data = $request->validate(['classId' => ['nullable', 'integer'], 'reason' => ['required', Rule::in(StudentPasswords::REASONS)]]);
        $passwords->reset($user, $request->user(), $data['reason'], $data['classId'] ?? null);

        return response()->json(['message' => 'Password reset. The student must sign in with their student ID as the temporary password and choose a new password. Previous sessions no longer have course access.'])->header('Cache-Control', 'no-store');
    }

    public function history(Request $request, User $user, StudentPasswords $passwords)
    {
        $data = $request->validate(['classId' => ['nullable', 'integer']]);
        $passwords->authorize($request->user(), $user, $data['classId'] ?? null);
        $events = RosterEvent::where('user_id', $user->id)->whereIn('action', ['student_initial_password_set', 'student_password_reset', 'student_password_changed'])
            ->latest('id')->limit(50)->get()->map(fn ($event) => ['id' => $event->id, 'action' => $event->action,
                'actorId' => $event->actor_id, 'actorRole' => $event->details['actor_role'] ?? 'staff',
                'reason' => $event->details['reason'] ?? null, 'at' => $event->created_at->toIso8601String()]);

        return response()->json(['events' => $events])->header('Cache-Control', 'no-store');
    }
}
