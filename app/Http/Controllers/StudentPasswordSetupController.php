<?php

namespace App\Http\Controllers;

use App\Services\StudentEmailSetup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class StudentPasswordSetupController extends Controller
{
    private function guest(Request $request, StudentEmailSetup $setup): void
    {
        abort_if($request->user(), 403, 'Sign out before setting up a student account.');
        abort_unless($setup->passwordReady(), 503, 'Student password setup is not available. Ask your administrator for help.');
    }

    public function status(Request $request, StudentEmailSetup $setup)
    {
        abort_if($request->user(), 403);

        return response()->json($setup->passwordStatus($request))->header('Cache-Control', 'no-store');
    }

    public function claim(Request $request, StudentEmailSetup $setup)
    {
        $this->guest($request, $setup);
        $data = $request->validate(['identityNumber' => ['required', 'string', 'max:80'], 'setupCode' => ['required', 'string', 'max:32']]);

        return response()->json($setup->claimPassword($request, $data['identityNumber'], $data['setupCode']))->header('Cache-Control', 'no-store');
    }

    public function complete(Request $request, StudentEmailSetup $setup)
    {
        $this->guest($request, $setup);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $setup->completePassword($request, $data['password']);

        return response()->json(['message' => 'Account activated. Sign in with your student ID and password.'])->header('Cache-Control', 'no-store');
    }
}
