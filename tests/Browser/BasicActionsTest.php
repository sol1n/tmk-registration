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

class BasicActionsTest extends DuskTestCase
{
    /**
     * Basic form data
     * @return array
     */
    private function participant()
    {
        return [
            'lastName' => 'lastName',
            'firstName' => 'firstName',
            'middleName' => 'middleName',
            'position' => 'position',
            'phoneNumber' => '+79999999999',
            'description' => 'description',
            'rewards' => 'rewards',
        ];
    }

    /**
     * Returns companies list that is available to the user in the url format
     * @return Illuminate\Support\Collection
     */
    private function getCompaniesList()
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
        ])->map(function ($item) {
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
     * Checks correctness form-added users data
     * @group creation
     * @group form
     */
    public function testAddedMemberHasCorrectData()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->participant();
            $userCompanies = $this->getCompaniesList();

            $browser->visit(new FormPage($userCompanies->first()))
                ->createParticipant($participant)
                ->assertSee($participant['firstName']);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);
            
            foreach ($participant as $field => $value) {
                $this->assertEquals($profile->fields[$field], $value);
            }

            app(UserManager::class)->delete($profile->fields['userId']);
            app(ObjectManager::class)->delete($userProfilesSchema, $profile->id);

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks that users added by form can login into appercode backend,
     * and correctness of generated codes
     * @group creation
     * @group form
     */
    public function testAddedMemberCanStartAppercodeSession()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->participant();
            $userCompanies = $this->getCompaniesList();

            $browser->visit(new FormPage($userCompanies->first()))
                ->createParticipant($participant)
                ->assertSee($participant['firstName']);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);

            $code = $profile->fields['code'] ?? '';

            $this->assertTrue(is_numeric($code));
            $this->assertEquals(mb_strlen($code), 6);
            
            $session = User::loginByCode($code);

            $this->assertTrue(isset($session['sessionId']) && !empty($session['sessionId']));
            $this->assertTrue(isset($session['userId']) && is_numeric($session['userId']));

            app(UserManager::class)->delete($profile->fields['userId']);
            app(ObjectManager::class)->delete($userProfilesSchema, $profile->id);

            $browser->visit(new FormPage)->logOff();
        });
    }
}
