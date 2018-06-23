<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

use App\User;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Services\UserManager;

use Tests\Browser\Pages\LoginPage;
use Tests\Browser\Pages\Form as FormPage;

class FormComponentsTest extends DuskTestCase
{
    /**
     * Returns companies list that is available to the user
     * @return Illuminate\Support\Collection
     */
    private function getCompanies()
    {
        $user = app(UserManager::class)->search(['where' => json_encode(['username' => env('USER')])])->first();
        $profile = app(ObjectManager::class)->search(app(SchemaManager::class)->find('UserProfiles'), ['where' => ['userId' => $user->id]])->first();

        return app(ObjectManager::class)->search(app(SchemaManager::class)->find('Companies'), [
            'take' => -1,
            'where' => [
                'id' => [
                    '$in' => $profile->fields['companies'] ?? []
                ]
            ]
        ]);
    }

    /**
     * Returns companies list that is available to the user in the url format
     * @return Illuminate\Support\Collection
     */
    private function getCompaniesList()
    {
        return $this->getCompanies()->map(function ($item) {
            return "/form/{$item->id}/";
        });
    }

    /**
     * Checks form has correct companies list that matches administrator data
     * @group form
     */
    public function testCompaniesList()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $userCompanies = $this->getCompaniesList();

            $browser->visit(new FormPage)
                ->assertSelectHasOptions('company', $userCompanies->toArray())
                ->select('company', $userCompanies->first())
                ->assertPathIs($userCompanies->first())
                ->assertSelected('company', $userCompanies->first())
                ->logOff();
        });
    }

    /**
     * Checks that form contains correct statuses list
     * @group form
     */
    public function testStatusesList()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $schema = app(SchemaManager::class)->find('Statuses');
            $statuses = app(ObjectManager::class)->search($schema, ['take' => -1])->map(function ($item) {
                return $item->id;
            });

            $browser->visit(new FormPage($this->getCompaniesList()->first()))
                ->assertSelectHasOptions('@creationStatusesList', $statuses->toArray());

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks that form contains correct sections list on lecture part
     * @group form
     */
    public function testSectionsList()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $schema = app(SchemaManager::class)->find('Sections');
            $sections = app(ObjectManager::class)->search($schema, ['take' => -1])->map(function ($item) {
                return $item->id;
            });

            $browser->visit(new FormPage($this->getCompaniesList()->first()))
                ->assertSelectHasOptions('@creationSectionsList', $sections->toArray());

            $browser->visit(new FormPage)->logOff();
        });
    }
}
