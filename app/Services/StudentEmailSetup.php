<?php

namespace App\Services;

use App\Mail\StudentSetupOtp;
use App\Models\AccountActivationToken;
use App\Models\ExternalIdentity;
use App\Models\RosterEvent;
use App\Models\StudentSetupChallenge;
use App\Models\StudentSetupCode;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentEmailSetup
{
    public function mailConfigured(): bool
    {
        if (config('mail.default') === 'gmail') {
            return app(GmailSender::class)->connected();
        }

        return config('mail.default') === 'smtp'
            && (bool) config('mail.mailers.smtp.host')
            && (bool) config('mail.mailers.smtp.username')
            && (bool) config('mail.mailers.smtp.password')
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && ! str_ends_with((string) config('mail.from.address'), '@example.com');
    }

    public function ready(): bool
    {
        return config('student-setup.enabled') && config('student-setup.delivery_verified') && $this->mailConfigured();
    }

    public function passwordReady(): bool
    {
        return (bool) config('student-setup.password_enabled');
    }

    public function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function eligible(User $user): bool
    {
        return $user->role === User::ROLE_STUDENT && $user->status === User::STATUS_PENDING
            && ! $user->email && ! ExternalIdentity::where('user_id', $user->id)->exists();
    }

    private function accountDigest(User $user): string
    {
        return $this->digest(json_encode(array_intersect_key($user->getRawOriginal(), array_flip([
            'id', 'identity_number', 'email', 'email_verified_at', 'password', 'role', 'status', 'updated_at',
        ])), JSON_THROW_ON_ERROR));
    }

    public function issue(array $ids, User $admin): array
    {
        return DB::transaction(function () use ($ids, $admin): array {
            $users = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($users->count() !== count($ids) || $users->contains(fn ($user) => ! $this->eligible($user))) {
                throw ValidationException::withMessages(['userIds' => 'Select only pending student accounts without email. Refresh the roster if it changed.']);
            }
            $codes = [];
            foreach ($users as $user) {
                $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $plain = '';
                for ($i = 0; $i < 12; $i++) {
                    $plain .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $record = StudentSetupCode::firstOrNew(['user_id' => $user->id]);
                $record->fill(['created_by' => $admin->id, 'code_hash' => $this->digest($plain), 'expires_at' => now()->addDays(7), 'consumed_at' => null])->save();
                StudentSetupChallenge::where('user_id', $user->id)->delete();
                RosterEvent::create(['actor_id' => $admin->id, 'user_id' => $user->id, 'action' => 'student_setup_code_issued']);
                $codes[] = ['identityNumber' => $user->identity_number, 'code' => implode('-', str_split($plain, 4)), 'expiresAt' => $record->expires_at->toIso8601String()];
            }

            return $codes;
        });
    }

    public function claim(Request $request, string $identity, string $plain): array
    {
        $this->createClaim($request, $identity, $plain, 'email');

        return $this->status($request);
    }

    public function claimPassword(Request $request, string $identity, string $plain): array
    {
        $this->createClaim($request, $identity, $plain, 'password');

        return $this->passwordStatus($request);
    }

    private function createClaim(Request $request, string $identity, string $plain, string $method): void
    {
        $nonce = Str::random(64);
        $challenge = DB::transaction(function () use ($identity, $plain, $nonce, $method) {
            $user = User::whereRaw('UPPER(identity_number) = ?', [strtoupper(trim($identity))])->lockForUpdate()->first();
            $code = $user ? StudentSetupCode::where('user_id', $user->id)->lockForUpdate()->first() : null;
            $hash = $this->digest(strtoupper(str_replace(['-', ' '], '', $plain)));
            $matches = hash_equals($code?->code_hash ?? str_repeat('0', 64), $hash);
            if (! $matches || ! $user || ! $this->eligible($user) || ! $code || $code->consumed_at || $code->expires_at->isPast()) {
                throw ValidationException::withMessages(['setupCode' => 'These setup details are invalid or expired. Check them or ask your administrator for a new setup code.']);
            }
            StudentSetupChallenge::where('user_id', $user->id)->delete();

            return StudentSetupChallenge::create(['id' => (string) Str::uuid(), 'user_id' => $user->id,
                'student_setup_code_id' => $code->id, 'setup_hash' => $code->code_hash, 'method' => $method,
                'account_hash' => $this->accountDigest($user),
                'session_hash' => $this->digest($nonce), 'expires_at' => now()->addMinutes(30)]);
        });
        $request->session()->put('student_setup', ['id' => $challenge->id, 'nonce' => $nonce]);
    }

    private function locked(Request $request, string $method = 'email'): array
    {
        $session = $request->session()->get('student_setup', []);
        $reference = StudentSetupChallenge::find($session['id'] ?? '');
        $user = $reference ? User::whereKey($reference->user_id)->lockForUpdate()->first() : null;
        $code = $reference ? StudentSetupCode::whereKey($reference->student_setup_code_id)->lockForUpdate()->first() : null;
        $challenge = $reference ? StudentSetupChallenge::whereKey($reference->id)->lockForUpdate()->first() : null;
        if (! $user || ! $this->eligible($user) || ! $code || $code->consumed_at || $code->expires_at->isPast()
            || ! $challenge || $challenge->method !== $method || $challenge->expires_at->isPast()
            || ! hash_equals($challenge->setup_hash, $code->code_hash)
            || ! hash_equals($challenge->account_hash, $this->accountDigest($user))
            || ! hash_equals($challenge->session_hash, $this->digest((string) ($session['nonce'] ?? '')))) {
            throw ValidationException::withMessages(['setup' => 'Your setup session has expired or changed. Start again with your student ID and setup code.']);
        }

        return [$user, $code, $challenge];
    }

    public function passwordStatus(Request $request): array
    {
        if (! $this->passwordReady()) {
            return ['enabled' => false, 'stage' => 'claim'];
        }
        try {
            return DB::transaction(function () use ($request) {
                $this->locked($request, 'password');

                return ['enabled' => true, 'stage' => 'password'];
            });
        } catch (ValidationException) {
            return ['enabled' => true, 'stage' => 'claim'];
        }
    }

    public function completePassword(Request $request, string $password): void
    {
        DB::transaction(function () use ($request, $password) {
            [$user, $code] = $this->locked($request, 'password');
            $user->forceFill(['password' => $password, 'status' => User::STATUS_ACTIVE])->save();
            $code->update(['consumed_at' => now()]);
            AccountActivationToken::where('user_id', $user->id)->get()->each->delete();
            RosterEvent::create(['user_id' => $user->id, 'action' => 'student_activated', 'details' => ['method' => 'student_id_password']]);
            StudentSetupChallenge::where('user_id', $user->id)->delete();
        });
        $request->session()->forget('student_setup');
    }

    public function status(Request $request): array
    {
        if (! $this->ready()) {
            return ['enabled' => false, 'stage' => 'claim'];
        }
        try {
            return DB::transaction(function () use ($request) {
                [, $code, $challenge] = $this->locked($request);
                $verified = $challenge->verified_at && $challenge->verified_at->gt(now()->subMinutes(5));
                $email = $challenge->email;
                $masked = $email ? substr($email, 0, 1).'***@'.explode('@', $email)[1] : null;

                return ['enabled' => true, 'stage' => $verified ? 'password' : ($challenge->delivery_state === 'sent' && $challenge->otp_expires_at?->isFuture() ? 'otp' : 'email'),
                    'maskedEmail' => $masked, 'resendAt' => $code->last_sent_at?->copy()->addSeconds(60)->toIso8601String()];
            });
        } catch (ValidationException) {
            $request->session()->forget('student_setup');

            return ['enabled' => true, 'stage' => 'claim'];
        }
    }

    public function send(Request $request, string $email): array
    {
        $email = strtolower(trim($email));
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        [$id, $hash] = DB::transaction(function () use ($request, $email, &$otp) {
            [, $code, $challenge] = $this->locked($request);
            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'This email cannot be used for this setup. Check the address or contact your administrator.']);
            }
            if ($code->last_sent_at?->gt(now()->subSeconds(60))) {
                throw ValidationException::withMessages(['email' => 'Wait 60 seconds before requesting another code.']);
            }
            if (! $code->send_window_at || $code->send_window_at->lte(now()->subHour())) {
                $code->send_window_at = now();
                $code->send_count = 0;
            }
            if ($code->send_count >= 5) {
                throw ValidationException::withMessages(['email' => 'The hourly code limit was reached. Try again later.']);
            }
            $code->send_count++;
            $code->last_sent_at = now();
            $code->save();
            while ($challenge->otp_hash && hash_equals($challenge->otp_hash, $this->digest($challenge->id.'|'.$challenge->email.'|'.$otp))) {
                $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            $hash = $this->digest($challenge->id.'|'.$email.'|'.$otp);
            $challenge->fill(['email' => $email, 'otp_hash' => $hash, 'otp_expires_at' => now()->addMinutes(10),
                'otp_attempts' => 0, 'verified_at' => null, 'delivery_state' => 'pending'])->save();

            return [$challenge->id, $hash];
        });
        try {
            Mail::to($email)->send(new StudentSetupOtp($otp));
        } catch (Throwable) {
            StudentSetupChallenge::whereKey($id)->where('otp_hash', $hash)->update(['otp_hash' => null, 'verified_at' => null, 'delivery_state' => 'failed']);
            throw ValidationException::withMessages(['email' => 'The verification email could not be sent. Wait a minute and try again, or contact your administrator.']);
        }
        StudentSetupChallenge::whereKey($id)->where('otp_hash', $hash)->update(['delivery_state' => 'sent']);

        return $this->status($request);
    }

    public function verify(Request $request, string $otp): array
    {
        $valid = DB::transaction(function () use ($request, $otp) {
            [, , $challenge] = $this->locked($request);
            if ($challenge->delivery_state !== 'sent' || ! $challenge->otp_hash || ! $challenge->otp_expires_at?->isFuture() || $challenge->otp_attempts >= 5) {
                return false;
            }
            $challenge->otp_attempts++;
            $valid = hash_equals($challenge->otp_hash, $this->digest($challenge->id.'|'.$challenge->email.'|'.$otp));
            $challenge->verified_at = $valid ? now() : null;
            if ($valid || $challenge->otp_attempts >= 5) {
                $challenge->otp_hash = null;
            }
            $challenge->save();

            return $valid;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['otp' => 'This code is incorrect, expired or already used. Request a new code if needed.']);
        }

        return $this->status($request);
    }

    public function complete(Request $request, string $password): void
    {
        try {
            DB::transaction(function () use ($request, $password) {
                [$user, $code, $challenge] = $this->locked($request);
                if (! $challenge->email || ! $challenge->verified_at || $challenge->verified_at->lte(now()->subMinutes(5))) {
                    throw ValidationException::withMessages(['otp' => 'Verify your email again before setting a password.']);
                }
                if (User::whereRaw('LOWER(email) = ?', [$challenge->email])->exists()) {
                    throw ValidationException::withMessages(['email' => 'This email cannot be used. Start again or contact your administrator.']);
                }
                $user->forceFill(['email' => $challenge->email, 'email_verified_at' => now(), 'password' => $password, 'status' => User::STATUS_ACTIVE])->save();
                $code->update(['consumed_at' => now()]);
                AccountActivationToken::where('user_id', $user->id)->get()->each->delete();
                RosterEvent::create(['user_id' => $user->id, 'action' => 'student_activated', 'details' => ['method' => 'email_otp']]);
                StudentSetupChallenge::where('user_id', $user->id)->delete();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email cannot be used. Contact your administrator.']);
        }
        $request->session()->forget('student_setup');
    }

    public function cancel(Request $request): void
    {
        $session = $request->session()->get('student_setup', []);
        StudentSetupChallenge::whereKey($session['id'] ?? '')
            ->where('session_hash', $this->digest((string) ($session['nonce'] ?? '')))->delete();
        $request->session()->forget('student_setup');
    }
}
