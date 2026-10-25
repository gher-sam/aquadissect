<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GmailSender
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    private const FILE = 'mail/gmail-sender.enc';

    public function configured(): bool
    {
        return filled(config('gmail-sender.client_id')) && filled(config('gmail-sender.client_secret'))
            && filter_var(config('gmail-sender.email'), FILTER_VALIDATE_EMAIL)
            && filter_var(config('gmail-sender.redirect'), FILTER_VALIDATE_URL);
    }

    private function connection(): ?array
    {
        try {
            $value = Storage::disk('local')->get(self::FILE);
            $data = $value ? json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR) : null;

            return is_array($data) && ($data['client_id'] ?? null) === config('gmail-sender.client_id')
                && ($data['email'] ?? null) === strtolower((string) config('gmail-sender.email'))
                && ! empty($data['refresh_token']) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function connected(): bool
    {
        return $this->configured() && $this->connection() !== null
            && strtolower((string) config('mail.from.address')) === strtolower((string) config('gmail-sender.email'));
    }

    public function authorize(string $code, string $verifier): void
    {
        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('gmail-sender.client_id'), 'client_secret' => config('gmail-sender.client_secret'),
                'redirect_uri' => config('gmail-sender.redirect'), 'grant_type' => 'authorization_code',
                'code' => $code, 'code_verifier' => $verifier,
            ]);
            $data = $response->json();
            if (! $response->successful() || ! is_array($data) || empty($data['access_token']) || empty($data['refresh_token'])
                || ! in_array(self::SCOPE, explode(' ', $data['scope'] ?? ''), true)) {
                throw new RuntimeException;
            }
            $profile = Http::withToken($data['access_token'])->connectTimeout(5)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo');
            if (! $profile->successful() || $profile->json('email_verified') !== true
                || strtolower((string) $profile->json('email')) !== strtolower((string) config('gmail-sender.email'))) {
                throw new RuntimeException;
            }
            $stored = Crypt::encryptString(json_encode(['email' => strtolower($profile->json('email')),
                'client_id' => config('gmail-sender.client_id'), 'refresh_token' => $data['refresh_token'],
                'connected_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR));
            if (! Storage::disk('local')->put(self::FILE, $stored, ['visibility' => 'private'])) {
                throw new RuntimeException;
            }
        } catch (Throwable) {
            // Provider responses and tokens must never be attached to logged exceptions.
            throw new RuntimeException('Google email could not be connected. Check the approved sender and Google configuration.');
        }
    }

    public function send(string $raw): void
    {
        try {
            $connection = $this->connection();
            if (! $this->connected() || ! $connection) {
                throw new RuntimeException;
            }
            $token = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('gmail-sender.client_id'), 'client_secret' => config('gmail-sender.client_secret'),
                'refresh_token' => $connection['refresh_token'], 'grant_type' => 'refresh_token',
            ]);
            if (! $token->successful() || ! is_string($token->json('access_token')) || ! $token->json('access_token')) {
                throw new RuntimeException;
            }
            $sent = Http::withToken($token->json('access_token'))->connectTimeout(5)->timeout(15)
                ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                    'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
                ]);
            if (! $sent->successful() || ! $sent->json('id')) {
                throw new RuntimeException;
            }
        } catch (Throwable) {
            throw new RuntimeException('Google email delivery failed. Reconnect the sending account or check its delivery settings.');
        }
    }
}
