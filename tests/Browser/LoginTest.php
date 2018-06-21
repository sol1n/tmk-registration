<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LoginPage;
use Tests\Browser\Pages\Form as FormPage;

class LoginTest extends DuskTestCase
{
    /**
     * @group auth
     */
    public function testWrongLoginMessage()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)
                ->signIn([
                    'login' => 'wrong',
                    'password' => 'wrong'
                ])
                ->assertPathIs('/');
        });
    }

    /**
     * @group auth
     */
    public function testLogin()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)
                ->signIn([
                    'login' => env('USER'),
                    'password' => env('PASSWORD')
                ])
                ->assertPathIs('/form');
        });
    }

    /**
     * @group auth
     */
    public function testLogout()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new FormPage)
                ->logOff()
                ->assertPathIs('/');
        });
    }
}
