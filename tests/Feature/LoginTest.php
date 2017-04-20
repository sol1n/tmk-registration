<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class LoginTest extends TestCase
{

    public function testRedirectWithoutSession()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login/');
    }

    public function testNoRedirectWithSession()
    {
        $this->withSession(['session-token' => env('TEST_TOKEN')]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
