<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicReefDemoTest extends TestCase
{
    public function test_demo_is_public_and_does_not_start_or_change_a_portal_session(): void
    {
        $this->withoutVite();
        $response = $this->withUnencryptedCookie('aquadissect_logout_pending', '1')
            ->get('/demo/reef-quest');

        $response->assertOk()->assertViewIs('reef-quest-demo')
            ->assertSee('Student demo')->assertSee('Your answers and scores stay in this tab.')
            ->assertSee("connect-src 'none'; form-action 'none'", false);
        $this->assertSame([], $response->headers->getCookies());
    }
}
