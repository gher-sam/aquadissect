<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GmailSender;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GmailSenderController extends Controller
{
    public function index(GmailSender $sender)
    {
        return response()->view('gmail-sender', ['configured' => $sender->configured(), 'connected' => $sender->connected()])
            ->header('Cache-Control', 'no-store');
    }

    public function redirect(Request $request, GmailSender $sender)
    {
        abort_unless($sender->configured(), 503, 'The Google sending connection has not been configured yet.');
        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->forget(['google_intent', 'state']);
        $request->session()->put('gmail_sender_intent', ['state' => $state, 'verifier' => $verifier,
            'actor' => $request->user()->id, 'expires' => now()->addMinutes(10)->timestamp]);
        $query = http_build_query(['client_id' => config('gmail-sender.client_id'), 'redirect_uri' => config('gmail-sender.redirect'),
            'response_type' => 'code', 'scope' => 'openid email '.GmailSender::SCOPE, 'access_type' => 'offline',
            'prompt' => 'consent select_account', 'login_hint' => config('gmail-sender.email'), 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256']);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query)->header('Cache-Control', 'no-store');
    }

    public function callback(Request $request, GmailSender $sender)
    {
        $intent = $request->session()->pull('gmail_sender_intent');
        try {
            if (! $request->user()?->isActive() || $request->user()->role !== User::ROLE_ADMIN || ! $sender->configured()
                || ! is_array($intent) || ($intent['actor'] ?? null) !== $request->user()->id
                || ($intent['expires'] ?? 0) <= now()->timestamp || ! is_string($request->query('state'))
                || ! hash_equals($intent['state'] ?? '', $request->query('state')) || $request->has('error')
                || ! is_string($request->query('code')) || ! $request->query('code')) {
                throw new \RuntimeException;
            }
            $sender->authorize($request->query('code'), $intent['verifier']);

            return redirect()->route('gmail-sender.index')->with('gmail_sender_message', 'Google email connected. Student setup remains disabled until actual inbox delivery is confirmed.')->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return redirect()->route('gmail-sender.index')->with('gmail_sender_message', 'The sending account could not be connected. Access may have been cancelled, expired or denied. Check the configured Google sender and try again.')->header('Cache-Control', 'no-store');
        }
    }
}
