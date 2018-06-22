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
            'textFields' => [
                'lastName' => 'lastName',
                'firstName' => 'firstName',
                'middleName' => 'middleName',
                'position' => 'position',
                'phoneNumber' => '+79999999999',
                'description' => 'description',
                'rewards' => 'rewards'

            ],
            'listFields' => [
                'status' => [
                    '221b9fea-6586-4be4-ae9d-8bfac8817f56',
                    '70630e35-ab1c-4375-b76b-fc2aeb8b1128',
                    '8d6d5a04-dc49-4b72-8c9d-1c8d4194fda2'
                ],
                
            ],
            'lectures' => [
                [
                    'theses' => 'theses',
                    'subject' => 'subject',
                    'section' => 'daa72dc4-5fe4-4c3d-82e0-247e272a53ab',
                ]
            ]
        ];
    }

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
     * Removes profile, user & lectures after test
     * @param  App\Object
     * @return void
     */
    private function deleteMember($profile)
    {
        if (isset($profile->fields['lectures']) && $profile->fields['lectures']) {
            $lecturesSchema = app(SchemaManager::Class)->find('Lectures');
            foreach ($profile->fields['lectures'] as $lectureId) {
                app(ObjectManager::Class)->delete($lecturesSchema, $lectureId);
            }
        }
        $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
        app(UserManager::class)->delete($profile->fields['userId']);
        app(ObjectManager::class)->delete($userProfilesSchema, $profile->id);
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
     * Checks correctness form-added users data:
     *
     * personal data
     * statuses list
     * section field from member lectures
     * 
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
                ->assertSee($participant['textFields']['firstName']);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);
            
            foreach ($participant['textFields'] as $field => $value) {
                $this->assertEquals($profile->fields[$field], $value);
            }

            $this->assertEquals($profile->fields['status'], $participant['listFields']['status']);
            foreach ($participant['lectures'] as $lecture) {
                $this->assertContains($lecture['section'], $profile->fields['sections']);
            }

            $this->deleteMember($profile);

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
                ->createParticipant($participant);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);

            $code = $profile->fields['code'] ?? '';

            $this->assertTrue(is_numeric($code));
            $this->assertEquals(mb_strlen($code), 6);
            
            $session = User::loginByCode($code);

            $this->assertTrue(isset($session['sessionId']) && !empty($session['sessionId']));
            $this->assertTrue(isset($session['userId']) && is_numeric($session['userId']));

            $this->deleteMember($profile);

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks that users are created with correct groups list from:
     *
     * selected company
     * selected statuses
     * added lectures
     * 
     * @group creation
     * @group form
     */
    public function testGroupsSupport()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->participant();

            $browser->visit(new FormPage($this->getCompaniesList()->first()))
                ->createParticipant($participant);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $company = $this->getCompanies()->first();
            $companyGroup = $company->fields['groupId'] ?? null;

            $statusesGroups = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('Statuses'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $participant['listFields']['status']
                    ]
                ]
            ])->map(function($item) {
                return $item->fields['groupId'] ?? null;
            });

            $participantLecturesSections = collect($participant['lectures'])->map(function($item) {
                return $item['section'];
            });

            $lecturesGroups = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('Sections'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $participantLecturesSections->toArray()
                    ]
                ]
            ])->map(function($item) {
                return $item->fields['groupId'] ?? null;
            });

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);

            $this->assertContains($companyGroup, $profile->fields['groupIds']);
            foreach ($statusesGroups->toArray() as $statusesGroup) {
                $this->assertContains($statusesGroup, $profile->fields['groupIds']);
            }
            foreach ($lecturesGroups->toArray() as $lecturesGroup) {
                $this->assertContains($lecturesGroup, $profile->fields['groupIds']);
            }

            $this->deleteMember($profile);

            $browser->visit(new FormPage)->logOff();
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

            $schema = app(SchemaManager::Class)->find('Statuses');
            $statuses = app(ObjectManager::Class)->search($schema, ['take' => -1])->map(function($item) {
                return $item->id;
            });

            $browser->visit(new FormPage($this->getCompaniesList()->first()))
                ->assertSelectHasOptions('@creationStatusesList', $statuses->toArray());

            $browser->visit(new FormPage)->logOff();
        });
    }
}
