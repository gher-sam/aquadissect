<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GmailSender;
use App\Services\StudentEmailSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GmailSenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        config(['gmail-sender.client_id' => 'synthetic-client', 'gmail-sender.client_secret' => 'synthetic-secret',
            'gmail-sender.redirect' => 'https://example.test/auth/google/callback', 'gmail-sender.email' => 'sender@example.test',
            'mail.from.address' => 'sender@example.test', 'mail.default' => 'gmail',
            'student-setup.enabled' => false, 'student-setup.delivery_verified' => false]);
        Storage::fake('local');
        Http::preventStrayRequests();
        Mail::forgetMailers();
    }

    public function test_connection_page_and_start_require_an_active_administrator(): void
    {
        $this->get('/admin/email-delivery')->assertRedirect('/login');
        $this->post('/auth/gmail/connect')->assertRedirect('/login');
        foreach (['student', 'instructor', 'program_head'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']))->get('/admin/email-delivery')->assertForbidden();
            $this->post('/auth/gmail/connect')->assertForbidden();
        }
        $this->actingAs($this->admin)->get('/admin/email-delivery')->assertOk()->assertSee('Connect Google email')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_authorization_uses_send_only_permission_offline_access_and_browser_bound_pkce(): void
    {
        $response = $this->actingAs($this->admin)->post('/auth/gmail/connect')->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('openid email '.GmailSender::SCOPE, $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('sender@example.test', $query['login_hint']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $intent = session('gmail_sender_intent');
        $this->assertSame($intent['state'], $query['state']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $intent['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertStringNotContainsString('synthetic-secret', $response->headers->get('Location'));
        Http::assertNothingSent();
    }

    public function test_valid_callback_stores_an_encrypted_private_credential_without_enabling_students(): void
    {
        $this->connect();
        $this->assertTrue(app(GmailSender::class)->connected());
        $stored = Storage::disk('local')->get('mail/gmail-sender.enc');
        $this->assertStringNotContainsString('synthetic-refresh', $stored);
        $this->assertStringNotContainsString('sender@example.test', $stored);
        $this->assertFalse(app(StudentEmailSetup::class)->ready());
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(session('gmail_sender_intent'));
        $this->get('/auth/google/callback?code=reused&state=reused')->assertRedirect('/login');
        $this->assertTrue(app(GmailSender::class)->connected());
    }

    public function test_invalid_state_expiry_denial_and_changed_actor_never_exchange_credentials(): void
    {
        foreach (['state', 'expiry', 'denial', 'actor'] as $case) {
            $this->actingAs($this->admin)->post('/auth/gmail/connect');
            $intent = session('gmail_sender_intent');
            if ($case === 'expiry') {
                $intent['expires'] = now()->subSecond()->timestamp;
            }
            if ($case === 'actor') {
                $intent['actor'] = $this->admin->id + 1;
            }
            $query = ['state' => $case === 'state' ? 'wrong' : $intent['state'], 'code' => 'synthetic-code'];
            if ($case === 'denial') {
                $query['error'] = 'access_denied';
            }
            $this->withSession(['gmail_sender_intent' => $intent])->get('/auth/google/callback?'.http_build_query($query))->assertRedirect('/admin/email-delivery');
            $this->assertFalse(app(GmailSender::class)->connected());
            $this->assertNull(session('gmail_sender_intent'));
        }
        Http::assertNothingSent();
    }

    public function test_wrong_account_missing_refresh_and_missing_permission_fail_closed(): void
    {
        foreach (['account', 'unverified', 'refresh', 'scope'] as $case) {
            $this->actingAs($this->admin)->post('/auth/gmail/connect');
            $state = session('gmail_sender_intent.state');
            $token = ['access_token' => 'synthetic-access', 'refresh_token' => 'synthetic-refresh', 'scope' => GmailSender::SCOPE];
            if ($case === 'refresh') {
                unset($token['refresh_token']);
            }
            if ($case === 'scope') {
                $token['scope'] = 'openid email';
            }
            Http::fake(['oauth2.googleapis.com/token' => Http::response($token),
                'openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => $case === 'account' ? 'other@example.test' : 'sender@example.test', 'email_verified' => $case !== 'unverified'])]);
            $this->get('/auth/google/callback?'.http_build_query(['state' => $state, 'code' => 'synthetic-code']))->assertRedirect('/admin/email-delivery');
            $this->assertFalse(app(GmailSender::class)->connected());
            Storage::disk('local')->assertMissing('mail/gmail-sender.enc');
        }
    }

    public function test_mail_transport_refreshes_privately_and_sends_mime_via_gmail_api(): void
    {
        $this->connect();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'synthetic-access']),
            'gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'synthetic-message'])]);
        Mail::raw('Synthetic delivery test body.', fn ($m) => $m->to('recipient@example.test')->subject('Synthetic test'));
        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
                return false;
            }
            $mime = base64_decode(strtr($request['raw'], '-_', '+/'));

            return $request->hasHeader('Authorization', 'Bearer synthetic-access')
                && str_contains($mime, 'Synthetic delivery test body.') && str_contains($mime, 'To: recipient@example.test');
        });
    }

    public function test_revoked_authorization_and_provider_errors_expose_no_provider_response_or_credentials(): void
    {
        $this->connect();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'private' => 'synthetic-private-diagnostic'], 400)]);
        try {
            Mail::raw('Synthetic body.', fn ($m) => $m->to('recipient@example.test')->subject('Test'));
            $this->fail('A revoked connection must fail closed.');
        } catch (RuntimeException $error) {
            $this->assertSame('Google email delivery failed. Reconnect the sending account or check its delivery settings.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/messages/send'));
    }

    public function test_changed_sender_or_client_invalidates_the_connection_and_delivery_gate(): void
    {
        $this->connect();
        config(['student-setup.enabled' => true, 'student-setup.delivery_verified' => true]);
        $this->assertTrue(app(StudentEmailSetup::class)->ready());
        config(['gmail-sender.client_id' => 'replacement-client']);
        $this->assertFalse(app(StudentEmailSetup::class)->ready());
        config(['gmail-sender.client_id' => 'synthetic-client', 'mail.from.address' => 'other@example.test']);
        $this->assertFalse(app(StudentEmailSetup::class)->ready());
    }

    private function connect(): void
    {
        $this->actingAs($this->admin)->post('/auth/gmail/connect');
        $state = session('gmail_sender_intent.state');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'synthetic-access', 'refresh_token' => 'synthetic-refresh', 'scope' => GmailSender::SCOPE]),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'sender@example.test', 'email_verified' => true])]);
        $this->get('/auth/google/callback?'.http_build_query(['state' => $state, 'code' => 'synthetic-code']))->assertRedirect('/admin/email-delivery');
    }
}
