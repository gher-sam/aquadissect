<?php

namespace App\Http\Controllers;

use App\Models\AccountActivationToken;
use App\Models\ExternalIdentity;
use App\Models\RosterEvent;
use App\Models\User;
use App\Services\DelayedCloneWrite;
use App\Services\GmailSender;
use App\Support\GoogleAccess;
use GuzzleHttp\Client;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, string $intent)
    {
        abort_unless(GoogleAccess::ready(), 503, 'Google sign-in is not available yet. Use your existing account access.');
        if ($request->user()) {
            return redirect()->route('portal.home');
        }
        $request->session()->forget('google_registration');
        $request->session()->forget('gmail_sender_intent');
        $request->session()->put('google_intent', ['action' => $intent, 'expires' => now()->addMinutes(10)->timestamp]);

        return Socialite::driver('google')->setScopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])->redirect()->header('Cache-Control', 'no-store');
    }

    public function callback(Request $request, DelayedCloneWrite $writes)
    {
        if ($request->session()->has('gmail_sender_intent')) {
            return app(GmailSenderController::class)->callback($request, app(GmailSender::class));
        }

        $intent = $request->session()->pull('google_intent');
        if (! GoogleAccess::ready() || ! is_array($intent) || ($intent['expires'] ?? 0) < now()->timestamp) {
            return $this->failure('This sign-in has expired. Please start again.');
        }
        if ($request->user()) {
            return redirect()->route('portal.home');
        }
        if ($request->has('error')) {
            $request->session()->forget('state');

            return $this->failure('Google sign-in was cancelled. You can start again when ready.');
        }
        try {
            // The stateful provider validates and consumes the session-bound OAuth state.
            $profile = Socialite::driver('google')->setHttpClient(new Client(['timeout' => 15, 'connect_timeout' => 5]))->user();
            $email = strtolower(trim((string) $profile->getEmail()));
            $subject = (string) $profile->getId();
            $verified = $profile->user['email_verified'] ?? $profile->user['verified_email'] ?? false;
            if (! in_array($verified, [true, 1, 'true', '1'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 255 || ! $subject || strlen($subject) > 255) {
                return $this->failure('Choose a Google account with a verified email address.');
            }

            $user = $writes->transaction(function () use ($subject, $email) {
                $identity = ExternalIdentity::query()->where('provider', 'google')->where('subject', $subject)->lockForUpdate()->first();
                $user = $identity ? User::query()->lockForUpdate()->find($identity->user_id) : null;
                if (! $identity) {
                    $matches = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->get();
                    if ($matches->count() > 1) {
                        throw ValidationException::withMessages(['email' => 'Ask the administrator to resolve duplicate email records.']);
                    }
                    $user = $matches->first();
                    if ($user && ExternalIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->exists()) {
                        throw ValidationException::withMessages(['email' => 'This account is already connected to another Google account. Ask the administrator for help.']);
                    }
                    if ($user && ($user->isActive() || ($user->status === User::STATUS_PENDING && in_array($user->role, [User::ROLE_STUDENT, User::ROLE_INSTRUCTOR], true)))) {
                        $identity = ExternalIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'subject' => $subject, 'verified_email' => $email, 'verified_at' => now()]);
                    }
                }
                if (! $user || ! $identity) {
                    return null;
                }
                $identity->update(['verified_email' => $email, 'verified_at' => now()]);
                if (! $user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
                if ($user->status === User::STATUS_PENDING && $user->role === User::ROLE_STUDENT && ! $user->must_change_password) {
                    $user->update(['status' => User::STATUS_ACTIVE, 'password' => null]);
                    AccountActivationToken::query()->where('user_id', $user->id)->get()->each->delete();
                    RosterEvent::query()->create(['user_id' => $user->id, 'action' => 'student_activated', 'details' => ['method' => 'google']]);
                }

                return $user;
            });
            if ($user) {
                if (! $user->isActive() && ! ($user->role === User::ROLE_STUDENT && $user->must_change_password)) {
                    return $this->failure('Your instructor request is waiting for administrator approval.');
                }
                Auth::login($user);
                $request->session()->regenerate();
                if ($user->role === User::ROLE_STUDENT) {
                    $request->session()->put('student_password_revision', $user->password_revision);
                }

                return redirect()->route('portal.home')->header('Cache-Control', 'no-store');
            }
            if ($intent['action'] !== 'register') {
                return $this->failure('This email is not on your account record. Ask the administrator to check your registered email.');
            }
            // Keep only the short-lived verified claim, never provider tokens or avatars.
            $request->session()->regenerate();
            $request->session()->put('google_registration', [
                'subject' => $subject, 'email' => $email,
                'first_name' => mb_substr((string) ($profile->user['given_name'] ?? ''), 0, 80),
                'last_name' => mb_substr((string) ($profile->user['family_name'] ?? ''), 0, 80),
                'expires' => now()->addMinutes(10)->timestamp,
            ]);

            return redirect()->route('portal.register')->header('Cache-Control', 'no-store');
        } catch (ValidationException $exception) {
            return $this->failure(collect($exception->errors())->flatten()->first());
        } catch (Throwable) {
            // Provider errors can contain credentials; do not log the response or exception.
            return $this->failure('Google sign-in could not finish. Please try again or contact the administrator.');
        }
    }

    public function register(Request $request, DelayedCloneWrite $writes)
    {
        abort_if($request->user(), 403);
        abort_unless(GoogleAccess::ready(), 503);
        $claim = $request->session()->get('google_registration');
        if (! is_array($claim) || ($claim['expires'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['email' => 'Your Google verification expired. Connect your Google account again.']);
        }
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'identityNumber' => ['required', 'string', 'max:80', 'unique:users,identity_number'],
            'agreement' => ['accepted'],
        ]);
        try {
            $writes->transaction(function () use ($claim, $data): void {
                if (User::query()->whereRaw('LOWER(email) = ?', [$claim['email']])->exists()
                    || ExternalIdentity::query()->where('provider', 'google')->where('subject', $claim['subject'])->exists()) {
                    throw ValidationException::withMessages(['email' => 'An account already exists. Return to sign in.']);
                }
                $user = User::query()->create(['first_name' => $data['firstName'], 'last_name' => $data['lastName'],
                    'email' => $claim['email'], 'identity_number' => $data['identityNumber'],
                    'role' => User::ROLE_INSTRUCTOR, 'status' => User::STATUS_PENDING, 'password' => null]);
                $user->forceFill(['email_verified_at' => now()])->save();
                ExternalIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'subject' => $claim['subject'],
                    'verified_email' => $claim['email'], 'verified_at' => now()]);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['email' => 'These account details are already in use. Return to sign in or ask the administrator for help.']);
        }
        $request->session()->forget('google_registration');

        return response()->json(['message' => 'Instructor request submitted. Use Google to sign in after administrator approval.'], 201)->header('Cache-Control', 'no-store');
    }

    private function failure(string $message)
    {
        return redirect()->route('login')->with('google_message', $message)->header('Cache-Control', 'no-store');
    }
}
