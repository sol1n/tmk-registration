<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\User;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Exceptions\User\WrongCredentialsException;
use App\Exceptions\User\UnAuthorizedException;

class UserTest extends TestCase
{
    public function test_user_can_login()
    {
        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->assertTrue(! empty($user->token()));
    }

    public function test_user_cant_login_with_bad_credentials()
    {
        $this->expectException(WrongCredentialsException::class);

        $user = User::Login([
            'login' => 'wrongLogin',
            'password' => 'wrongPassword'
        ], false);
    }

    public function test_unauthorized_user_cant_has_token()
    {
        $this->expectException(UnAuthorizedException::class);
        $user = new User;
        $token = $user->token();
    }

    public function test_can_regenerate_token()
    {
        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $token = $user->token();
        $user->regenerate(false);
        $this->assertNotEquals($token, $user->token());
    }
}
