<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

use App\User;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Services\UserManager;
use App\Services\TmkHelper;

use Tests\Browser\Pages\LoginPage;
use Tests\Browser\Pages\Form as FormPage;

class BasicActionsTest extends DuskTestCase
{
    const EVENT_GROUP = '1ad70d49-3efc-436c-b806-4a303aa2679c';
    const KVN_STATUS = 'cad65dda-7add-4465-9a3a-744e7378752a';
    const FOOTBALL_STATUS = '6e1fca1c-5ad6-4105-a590-13adeeea0737';
    const EXPERT_STATUS = '9fe747c3-5020-44fd-b97b-00fce46fa42a';
    const DEFAULT_ROLE = 'Participant';

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
                ],
                [
                    'theses' => 'theses2',
                    'subject' => 'subject2',
                    'section' => '0421f11d-9287-4ac6-adcc-5802bbf316ef',
                ]
            ],
            'en' => [
                'lastName' => 'lastNameEn',
                'firstName' => 'firstNameEn',
                'middleName' => 'middleNameEn',
                'description' => 'descriptionEn',
                'rewards' => 'rewardsEn'
            ]
        ];
    }

    private function getExpertSection()
    {
        return '932c6cc9-dc6e-4e7f-87e6-8944816cc9c5';
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
        $participant['listFields']['KVNTeam'] = app(ObjectManager::class)->search(app(SchemaManager::class)->find('KVNTeams'), [
            'take' => 1
        ])->first()->id;
        $participant['listFields']['footballTeam'] = app(ObjectManager::class)->search(app(SchemaManager::class)->find('footballTeam'), [
            'take' => 1
        ])->first()->id;

        return $participant;
    }

    private function expertParticipantWithLectures()
    {
        $participant = $this->participant();
        $participant['listFields']['status'][] = self::EXPERT_STATUS;
        $participant['listFields']['memberSections'][] = $this->getExpertSection();

        return $participant;
    }

    private function expertParticipant()
    {
        $participant = $this->expertParticipantWithLectures();
        $participant['lectures'] = [];

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
        $lecturesSchema = app(SchemaManager::class)->find('Sections');
        $memberLectures = app(ObjectManager::class)->search($lecturesSchema, [
            'take' => -1,
            'where' => [
                'userProfileIds' => [
                    '$contains' => [$profile->id]
                ]
            ]
        ]);

        if ($memberLectures->count()) {
            foreach ($memberLectures as $lecture) {
                if (TmkHelper::isReport($lecture)) {
                    app(ObjectManager::class)->delete($lecturesSchema, $lecture->id);
                }
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
     * english fields
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
            $profile = app(ObjectManager::class)->allWithLang($userProfilesSchema, [
                'take' => 1,
                'where' => [
                    'id' => $id
                ]
            ], 'en')->first();
            
            // personal data
            foreach ($participant['textFields'] as $field => $value) {
                $this->assertEquals($profile->fields[$field], $value);
            }

            // english fields of personal data
            foreach ($participant['en'] as $field => $value) {
                $this->assertEquals($profile->languages['en'][$field], $value);
            }

            // statuses list
            $this->assertEquals($profile->fields['status'], $participant['listFields']['status']);

            // sections list should be empty for non-expert profiles
            $this->assertEmpty($profile->fields['sections']);

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
            $this->assertEquals($session['roleId'], self::DEFAULT_ROLE);

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
            $kvnTeamGroup = app(ObjectManager::class)->find(app(SchemaManager::class)->find('KVNTeams'), $participant['listFields']['KVNTeam'])
                ->fields['groupId'];
            $this->assertContains($kvnTeamGroup, $profile->fields['groupIds']);

            // Selected Fooltball team group
            $footballTeamGroup = app(ObjectManager::class)->find(app(SchemaManager::class)->find('footballTeam'), $participant['listFields']['footballTeam'])
                ->fields['groupId'];
            $this->assertContains($kvnTeamGroup, $profile->fields['groupIds']);

            $this->deleteMember($profile);
            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks that user has correct team fields and statuses after setting kvn & football team
     * @group creation
     */
    public function testFootballAndKVNMemberCorrectData()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->footballAndKVNParticipant();
            $userCompanies = $this->getCompaniesList();

            $browser->visit(new FormPage($userCompanies->first()))
                ->createParticipant($participant)
                ->assertSee($participant['textFields']['firstName']);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);

            $this->assertEquals($profile->fields['KVNTeam'], $participant['listFields']['KVNTeam']);
            $this->assertEquals($profile->fields['footballTeam'], $participant['listFields']['footballTeam']);

            $this->assertContains(self::FOOTBALL_STATUS, $profile->fields['status']);
            $this->assertContains(self::KVN_STATUS, $profile->fields['status']);

            $this->deleteMember($profile);

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks form delete action that should delete:
     *
     * user
     * user profile
     * user lectures
     *
     * @group creation
     * @group deleting
     */
    public function testDeleting()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->expertParticipantWithLectures();
            $userCompanies = $this->getCompaniesList();

            $browser->visit(new FormPage($userCompanies->first()))
                ->createParticipant($participant);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->find($userProfilesSchema, $id);

            $browser->visit(new FormPage($userCompanies->first()))
                ->deleteParticipant($id)
                ->assertDontSee($participant['textFields']['firstName']);

            // profile check
            $profileAfterDeleting = app(ObjectManager::class)->search($userProfilesSchema, [
                'take' => 1,
                'where' => [
                    'id' => $profile->id
                ]
            ])->first();
            $this->assertNull($profileAfterDeleting);

            // lectures list check
            $lecturesSchema = app(SchemaManager::class)->find('Sections');
            $lectureAfterDeleting = app(ObjectManager::class)->search($lecturesSchema, [
                'take' => 1,
                'where' => [
                    'userProfileIds' => [
                        '$contains' => [$profile->id]
                    ],
                    'parentId' => [
                        '$nin' => array_keys(TmkHelper::GENERAL_SECTIONS)
                    ]
                ]
            ])->first();
            $this->assertNull($lectureAfterDeleting);

            // user check
            $userAfterDeleting = app(UserManager::class)->search([
                'take' => 1,
                'where' => json_encode([
                    'id' => $profile->fields['userId']
                ])
            ])->first();
            $this->assertNull($userAfterDeleting);

            // section check
            $section = app(ObjectManager::class)->search($lecturesSchema, [
                'take' => 1,
                'where' => [
                    'id' => $this->getExpertSection()
                ]
            ])->first();
            $this->assertNotNull($section);

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks that lectures is creating with correct data
     * @group creation
     * @group current
     */
    public function testLecturesCreation()
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

            $profileId = $browser->attribute('.js-members-table-row-edit', 'data-member-id');

            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->allWithLang($userProfilesSchema, [
                'take' => 1,
                'where' => [
                    'id' => $profileId
                ]
            ], 'en')->first();

            $lecturesSchema = app(SchemaManager::class)->find('Sections');
            $lectures = app(ObjectManager::class)->allWithLang($lecturesSchema, [
                'take' => -1,
                'order' => 'createdAt',
                'where' => [
                    'userProfileIds' => [
                        '$contains' => [$profileId]
                    ]
                ]
            ], 'en');

            foreach ($participant['lectures'] as $index => $lecture) {
                $renderedDescription = view('lectures/description', [
                    'title' => $lecture['subject'],
                    'description' => $lecture['theses']
                ])->render();

                $this->assertEquals($renderedDescription, $lectures[$index]->fields['description']);
                $this->assertEquals($lecture['subject'], $lectures[$index]->fields['title']);
                $this->assertEquals($lecture['section'], $lectures[$index]->fields['parentId']);
                $this->assertEquals($lectures[$index]->fields['subtitle'], $profile->fields['lastName'] . ' ' . $profile->fields['firstName']);
                $this->assertEquals($lectures[$index]->languages['en']['subtitle'], $profile->languages['en']['lastName'] . ' ' . $profile->languages['en']['firstName']);
            }

            $this->deleteMember($profile);

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * Checks correction set of created member profile:
     *
     * company tag,
     * statuses tags,
     * lectures sections tags
     *
     * @group creation
     */
    public function testMemberTags()
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
            $profile = app(ObjectManager::class)->search($userProfilesSchema, [
                'take' => 1,
                'where' => [
                    'id' => $id
                ]
            ])->first();

            $tagSources = [
                $this->getCompanies()->first()->id
            ];
            foreach ($participant['listFields']['status'] as $status) {
                $tagSources[] = $status;
            }
            foreach ($participant['lectures'] as $lecture) {
                $tagSources[] = $lecture['section'];
            }

            $tagsSchema = app(SchemaManager::class)->find('UserProfilesTags');
            $tags =  app(ObjectManager::class)->search($tagsSchema, ['take' => -1])->map(function ($item) use ($tagSources) {
                if (isset($item->fields['external']) && in_array($item->fields['external'], $tagSources)) {
                    return $item->id;
                }
            })->filter()->values()->toArray();

            foreach ($tags as $tag) {
                $this->assertContains($tag, $profile->fields['tagsIds']);
            }

            $this->deleteMember($profile);

            $browser->visit(new FormPage)->logOff();
        });
    }

    /**
     * @group creation
     */
    public function testExpertAdditionsWithCorrectSections()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)->signIn([
                'login' => env('USER'),
                'password' => env('PASSWORD')
            ]);

            $participant = $this->expertParticipant();

            $userCompanies = $this->getCompaniesList();

            $browser->visit(new FormPage($userCompanies->first()))
                ->createParticipant($participant);

            $id = $browser->attribute('.js-members-table-row-edit', 'data-member-id');
            $userProfilesSchema = app(SchemaManager::class)->find('UserProfiles');
            $profile = app(ObjectManager::class)->search($userProfilesSchema, [
                'take' => 1,
                'where' => [
                    'id' => $id
                ]
            ])->first();

            $section = app(ObjectManager::class)->find(app(SchemaManager::class)->find('Sections'), self::getExpertSection());

            $this->assertFalse(TmkHelper::isReport($section));
            $this->assertNotEmpty($profile->fields['sections']);
            $this->assertContains($section->id, $profile->fields['sections']);
            $this->assertContains($profile->id, $section->fields['userProfileIds']);

            $tagSources = [$this->getExpertSection()];
            $tagsSchema = app(SchemaManager::class)->find('UserProfilesTags');
            $tags =  app(ObjectManager::class)->search($tagsSchema, [
                'take' => -1,
                'where' => [
                    'external' => [
                        '$in' => $tagSources
                    ]
                ]
            ])->map(function ($item) use ($tagSources) {
                if (isset($item->fields['external']) && in_array($item->fields['external'], $tagSources)) {
                    return $item->id;
                }
            })->filter()->values()->toArray();

            foreach ($tags as $tag) {
                $this->assertContains($tag, $profile->fields['tagsIds']);
            }

            $lecturesGroups = app(ObjectManager::class)->search(app(SchemaManager::class)->find('Sections'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => [self::getExpertSection()]
                    ]
                ]
            ])->map(function ($item) {
                return $item->fields['groupId'] ?? null;
            });
            foreach ($lecturesGroups->toArray() as $lecturesGroup) {
                $this->assertContains($lecturesGroup, $profile->fields['groupIds']);
            }

            $this->deleteMember($profile);

            $browser->visit(new FormPage)->logOff();
        });
    }
}
