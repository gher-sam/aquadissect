<?php

namespace App\Http\Controllers;

use App\Services\StudentEmailSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class StudentEmailSetupController extends Controller
{
    private function guest(Request $request, StudentEmailSetup $setup): void
    {
        abort_if($request->user(), 403, 'Sign out before setting up a student account.');
        abort_unless($setup->ready(), 503, 'Student email setup is not available yet. Ask your administrator for help.');
    }

    public function status(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        abort_if($request->user(), 403);

        return response()->json($setup->status($request))->header('Cache-Control', 'no-store');
    }

    public function issue(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        $data = $request->validate(['userIds' => ['required', 'array', 'min:1', 'max:1000'], 'userIds.*' => ['required', 'integer', 'distinct']]);

        return response()->json(['codes' => $setup->issue($data['userIds'], $request->user())])->header('Cache-Control', 'no-store');
    }

    public function claim(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        $this->guest($request, $setup);
        $data = $request->validate(['identityNumber' => ['required', 'string', 'max:80'], 'setupCode' => ['required', 'string', 'max:32']]);

        return response()->json($setup->claim($request, $data['identityNumber'], $data['setupCode']))->header('Cache-Control', 'no-store');
    }

    public function send(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        $this->guest($request, $setup);
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        return response()->json($setup->send($request, $data['email']))->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        $this->guest($request, $setup);
        $data = $request->validate(['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);

        return response()->json($setup->verify($request, $data['otp']))->header('Cache-Control', 'no-store');
    }

    public function complete(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        $this->guest($request, $setup);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $setup->complete($request, $data['password']);

        return response()->json(['message' => 'Account activated. Sign in, then open Home → Start orientation.'])->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request, StudentEmailSetup $setup): JsonResponse
    {
        abort_if($request->user(), 403);
        $setup->cancel($request);

        return response()->json(['cancelled' => true])->header('Cache-Control', 'no-store');
    }
}
