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
    const EVENT_GROUP = '1ad70d49-3efc-436c-b806-4a303aa2679c';
    const KVN_STATUS = 'cad65dda-7add-4465-9a3a-744e7378752a';
    const FOOTBALL_STATUS = '6e1fca1c-5ad6-4105-a590-13adeeea0737';

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
     * Extend basic participant by kvn & football teams and statuses
     * @return array
     */
    private function footballAndKVNParticipant()
    {
        $participant = $this->participant();
        $participant['listFields']['status'][] = self::KVN_STATUS;
        $participant['listFields']['status'][] = self::FOOTBALL_STATUS;
        $participant['listFields']['KVNTeam'] = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('KVNTeams'), [
            'take' => 1
        ])->first()->id;
        $participant['listFields']['footballTeam'] = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('footballTeam'), [
            'take' => 1
        ])->first()->id;

        return $participant;
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
            $lecturesSchema = app(SchemaManager::class)->find('Lectures');
            foreach ($profile->fields['lectures'] as $lectureId) {
                app(ObjectManager::class)->delete($lecturesSchema, $lectureId);
            }
        }
        $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
        app(UserManager::class)->delete($profile->fields['userId']);
        app(ObjectManager::class)->delete($userProfilesSchema, $profile->id);
    }

    /**
     * Checks correctness form-added users data:
     *
     * personal data
     * statuses list
     * section field from member lectures
     *
     * @group creation
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
     * common event group
     * kvn team group if selected
     * football team group if selected
     *
     * @group creation
     */
    public function testGroupsSupport()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->footballAndKVNParticipant();

            $browser->visit(new FormPage($this->getCompaniesList()->first()))
                ->createParticipant($participant);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);

            // Common event group
            $this->assertContains(self::EVENT_GROUP, $profile->fields['groupIds']);

            // Selected company group
            $company = $this->getCompanies()->first();
            $companyGroup = $company->fields['groupId'] ?? null;
            $this->assertContains($companyGroup, $profile->fields['groupIds']);

            // Member selected statuses groups
            $statusesGroups = app(ObjectManager::class)->search(app(SchemaManager::class)->find('Statuses'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $participant['listFields']['status']
                    ]
                ]
            ])->map(function ($item) {
                return $item->fields['groupId'] ?? null;
            });
            foreach ($statusesGroups->toArray() as $statusesGroup) {
                $this->assertContains($statusesGroup, $profile->fields['groupIds']);
            }

            // Lectures sections groups
            $participantLecturesSections = collect($participant['lectures'])->map(function ($item) {
                return $item['section'];
            });
            $lecturesGroups = app(ObjectManager::class)->search(app(SchemaManager::class)->find('Sections'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $participantLecturesSections->toArray()
                    ]
                ]
            ])->map(function ($item) {
                return $item->fields['groupId'] ?? null;
            });
            foreach ($lecturesGroups->toArray() as $lecturesGroup) {
                $this->assertContains($lecturesGroup, $profile->fields['groupIds']);
            }

            // Selected KVN team group
            $kvnTeamGroup = app(ObjectManager::Class)->find(app(SchemaManager::Class)->find('KVNTeams'), $participant['listFields']['KVNTeam'])
                ->fields['groupId'];
            $this->assertContains($kvnTeamGroup, $profile->fields['groupIds']);

            // Selected Fooltball team group
            $footballTeamGroup = app(ObjectManager::Class)->find(app(SchemaManager::Class)->find('footballTeam'), $participant['listFields']['footballTeam'])
                ->fields['groupId'];
            $this->assertContains($kvnTeamGroup, $profile->fields['groupIds']);

            $this->deleteMember($profile);
            $browser->visit(new FormPage)->logOff();
        });
    }
}
