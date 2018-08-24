<?php

namespace App\Services;

use App\User;
use App\Backend;
use App\Services\SchemaManager;
use App\Services\ObjectManager;
use App\Services\FileManager;

use Illuminate\Support\Facades\Cache;

use App\Exceptions\Site\EmptyCompanyList;

class TmkHelper
{
    const PHOTO_DIRECTORY = '892630b0-b07c-4cfa-9290-378cd0bfd16e';
    const PRESENTATION_DIRECTORY = '43308c2c-f012-4877-bd12-ffa697b5a42b';
    const EVENT_GROUP = '1ad70d49-3efc-436c-b806-4a303aa2679c';

    const GENERAL_SECTIONS = [
        '2756d0f5-2976-46ce-9d99-d7939bab960e' => 'ГС',
        '3e098ddf-41ef-4e98-95af-3c38da087bf7' => 'TMK'
    ];

    const CACHE_LIFETIME = 15;

    private $backend;

    public function __construct(Backend $backend)
    {
        $this->backend = $backend;
    }

    public function getRandomPassword(int $length = 6): string
    {
        return (string) rand(100000, 999999);
    }

    public function getCurrentUser()
    {
        $user = new User();

        return app(ObjectManager::class)->search(app(SchemaManager::class)->find('UserProfiles'), [
            'take' => 1,
            'where' => ['userId' => $user->id]
        ])->first();
    }

    public function checkCompanyAvailability($profile, $companyCode)
    {
        if (isset($profile->fields) and is_array($profile->fields) and isset($profile->fields['companies']) and is_array($profile->fields['companies']) and count($profile->fields['companies'])) {
            $schema = app(SchemaManager::class)->find('Companies');
            $companies = app(ObjectManager::class)->search($schema, [
                'take' => -1,
                'where' => ['id' => ['$in' => $profile->fields['companies']]]
            ])->mapWithKeys(function ($item) {
                return [$item->id => $item->fields];
            });
            
            if (! is_null($companyCode) and !isset($companies[$companyCode])) {
                throw new EmptyCompanyList('Can`t find company with code ' . $companyCode);
            }

            return $companies;
        } else {
            throw new EmptyCompanyList('Can`t process companies list');
        }
    }

    public function uploadFile($file, $parent)
    {
        $fileProperties = [
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'fileProperties' => [
                "rights" => [
                    "read" => true,
                    "write" => true,
                    "delete" => true
                ],
            ],
            "shareStatus" => "shared"
        ];

        $result = app(FileManager::class)->createFile($fileProperties, $parent);
        $uploadResult = app(FileManager::class)->uploadFile(
            $result['file']->id,
            $file
        );
        if ($uploadResult) {
            return $result['file']->id;
        } else {
            throw new EmptyCompanyList('Can`t upload file');
        }
    }

    public function uploadPhoto($file)
    {
        return $this->uploadFile($file, self::PHOTO_DIRECTORY);
    }

    public function uploadPresentation($file)
    {
        return $this->uploadFile($file, self::PRESENTATION_DIRECTORY);
    }

    public function getTags(array $fields, array $sections, string $companyId)
    {
        $externalIds = [$companyId];

        if (isset($fields['status']) && $fields['status']) {
            $externalIds = array_merge($externalIds, $fields['status']);
        }

        if ($sections) {
            $externalIds = array_merge($externalIds, $sections);
        }

        $tagsSchema = app(SchemaManager::class)->find('UserProfilesTags');
        return app(ObjectManager::class)->search($tagsSchema, ['take' => -1])->map(function ($item) use ($externalIds) {
            if (isset($item->fields['external']) && in_array($item->fields['external'], $externalIds)) {
                return $item->id;
            }
        })->filter()->values()->toArray();
    }

    /**
     * Returns groups for specified in input fields elements:
     *
     * company,
     * statuses,
     * sections,
     * KVN team,
     * Football team
     *
     * @param  array $fields    input data
     * @param  string $companyId selected company
     * @param  array $sections sections list by lectures
     * @return array
     */
    public function getGroups(array $fields, array $sections, string $companyId): array
    {
        $groups = [self::EVENT_GROUP];

        // Company group
        $companySchema = app(SchemaManager::class)->find('Companies');
        $company = app(ObjectManager::class)->find($companySchema, $companyId);
        if (isset($company->fields['groupId']) && $company->fields['groupId']) {
            $groups[] = $company->fields['groupId'];
        }

        // Member statuses group
        if (isset($fields['status']) && $fields['status']) {
            $memberStatuses = app(ObjectManager::class)->search(app(SchemaManager::class)->find('Statuses'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $fields['status']
                    ]
                ]
            ]);
            if ($memberStatuses->isNotEmpty()) {
                foreach ($memberStatuses as $status) {
                    if (isset($status->fields['groupId']) && $status->fields['groupId']) {
                        $groups[] = $status->fields['groupId'];
                    }
                }
            }
        }

        // Member lectures sections groups
        if (count($sections) > 0) {
            $memberSections = app(ObjectManager::class)->search(app(SchemaManager::class)->find('Sections'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $sections
                    ]
                ]
            ]);
            if ($memberSections->isNotEmpty()) {
                foreach ($memberSections as $section) {
                    if (isset($section->fields['groupId']) && $section->fields['groupId']) {
                        $groups[] = $section->fields['groupId'];
                    }
                }
            }
        }

        // KNV team group if selected
        if (isset($fields['KVNTeam']) && $fields['KVNTeam']) {
            $team = app(ObjectManager::class)->search(app(SchemaManager::class)->find('KVNTeams'), [
                'take' => 1,
                'where' => [
                    'id' => $fields['KVNTeam']
                ]
            ])->first();

            if (!is_null($team) && isset($team->fields['groupId']) && $team->fields['groupId']) {
                $groups[] = $team->fields['groupId'];
            }
        }

        // Football team group if selected
        if (isset($fields['footballTeam']) && $fields['footballTeam']) {
            $team = app(ObjectManager::class)->search(app(SchemaManager::class)->find('footballTeam'), [
                'take' => 1,
                'where' => [
                    'id' => $fields['footballTeam']
                ]
            ])->first();

            if (!is_null($team) && isset($team->fields['groupId']) && $team->fields['groupId']) {
                $groups[] = $team->fields['groupId'];
            }
        }

        return $groups;
    }

    public function getLectureGroups()
    {
        return [self::EVENT_GROUP];
    }

    public function getStatuses()
    {
        if (Cache::has('statuses')) {
            return Cache::get('statuses');
        } else {
            $schema = app(SchemaManager::class)->find('Statuses');
            $statuses = app(ObjectManager::class)->search($schema, [
                'take' => -1,
                'order' => [
                    'orderIndex' => 'asc'
                ]
            ]);
            Cache::put('statuses', $statuses, self::CACHE_LIFETIME);
            return $statuses;
        }
    }

    public function getSections()
    {
        if (Cache::has('sections')) {
            return Cache::get('sections');
        } else {
            $schema = app(SchemaManager::class)->find('Sections');
            $sections = app(ObjectManager::class)->search($schema, [
                'take' => -1,
                'where' => [
                    'parentId' => [
                        '$in' => array_keys(self::GENERAL_SECTIONS)
                    ]
                ],
                'order' => [
                    'title' => 'asc'
                ]
            ])->mapWithKeys(function ($item) {
                $item->parent = isset($item->fields['parentId']) && $item->fields['parentId'] ? self::GENERAL_SECTIONS[$item->fields['parentId']] : '';
                return [$item->id => $item];
            });

            Cache::put('sections', $sections, self::CACHE_LIFETIME);
            return $sections;
        }
    }

    public function getCompanies($user, $companyCode = null)
    {
        $key = 'companies-' . $user->id;
        if (Cache::has($key)) {
            return Cache::get($key);
        } else {
            $companies = $this->checkCompanyAvailability($user, $companyCode);
            Cache::put($key, $companies, self::CACHE_LIFETIME);
            return $companies;
        }
    }

    public function getFootballTeams()
    {
        if (Cache::has('footballTeams')) {
            return Cache::get('footballTeams');
        } else {
            $schema = app(SchemaManager::class)->find('footballTeam');
            $teams = app(ObjectManager::class)->search($schema, ['take' => -1])->mapWithKeys(function ($item) {
                return [$item->id => $item->fields['title']];
            });
            Cache::put('footballTeams', $teams, self::CACHE_LIFETIME);
            return $teams;
        }
    }

    public function getKVNTeams()
    {
        if (Cache::has('KVNTeams')) {
            return Cache::get('KVNTeams');
        } else {
            $schema = app(SchemaManager::class)->find('KVNTeams');
            $teams = app(ObjectManager::class)->search($schema, ['take' => -1])->mapWithKeys(function ($item) {
                return [$item->id => $item->fields['Title']];
            });
            Cache::put('KVNTeams', $teams, self::CACHE_LIFETIME);
            return $teams;
        }
    }
}
