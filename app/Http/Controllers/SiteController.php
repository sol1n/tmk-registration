<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\ClientException;

use App\Services\TmkHelper;

use App\Services\ObjectManager;
use App\Services\SchemaManager;

use Illuminate\Pagination\LengthAwarePaginator;

class SiteController extends Controller
{
    const NEW_USER_ROLE = 'Participant';
    const LECTURE_STATUSES = [
        '07e6da63-a4d9-45fd-b181-58272cf40bb4',
        '221b9fea-6586-4be4-ae9d-8bfac8817f56'
    ];
    const FOOTBALL_STATUSES = [
        '6e1fca1c-5ad6-4105-a590-13adeeea0737'
    ];
    const KVN_STATUSES = [
        'cad65dda-7add-4465-9a3a-744e7378752a'
    ];
    const GROUP_TITLE = 'Доклады';
    const GROUP_TITLE_EN = 'Reports';

    private $helper;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!app(Backend::class)->authorized()) {
                return redirect()->route('index');
            }

            $this->helper = new TmkHelper(app(Backend::class));

            try {
                $schemaManager = app(SchemaManager::class);
            } catch (\Exception $e) {
                if ($e instanceof ClientException && $e->hasResponse() && $e->getResponse()->getStatusCode() == 401) {
                    User::forgetSession(app(Backend::class));
                    return redirect()->route('index');
                }
            }

            return $next($request);
        });
    }

    public function LectureForm()
    {
        return view('form/lecture', [
            'sections' => $this->helper->getSections(),
            'index' => request()->get('index') ?? 0,
            'statuses' => self::LECTURE_STATUSES,
            'active' => true,
            'showMoreButton' => true
        ]);
    }

    private function settings()
    {
        return [
            'sorting' => [
                'lastName' => 'По возрастанию ФИО',
                '-lastName' => 'По убыванию ФИО',
                'createdAt' => 'По возрастанию даты создания',
                '-createdAt' => 'По убыванию даты создания',
                'updatedAt' => 'По возрастанию даты обновления',
                '-updatedAt' => 'По убыванию даты обновления',
            ],
            'count' => [
                '25' => 25,
                '50' => 50,
                '100' => 100,
                '500' => 500,
                '1000' => 1000
            ]
        ];
    }

    public function ShowCompanySelect()
    {
        $user = $this->helper->getCurrentUser();
        $companies = $this->helper->getCompanies($user);

        return view('form', [
            'companies' => $companies
        ]);
    }

    public function ShowEditForm(Backend $backend, string $companyCode)
    {
        $user = $this->helper->getCurrentUser();

        $statuses = $this->helper->getStatuses();
        $sections = $this->helper->getSections();
        $KVNTeams = $this->helper->getKVNTeams();
        $footballTeams = $this->helper->getFootballTeams();
        $companies = $this->helper->getCompanies($user, $companyCode);
        
        $selectedSettings = [
            'count' => (int) (request()->get('count') ?? config('objects.objects_per_page')),
            'sorting' => request()->get('sorting') ?? '-updatedAt',
            'page' => (int) (request()->get('page') ?? 1)
        ];

        $schema = app(SchemaManager::class)->find('UserProfiles');
        $members = app(ObjectManager::class)->allWithLang($schema, [
            'order' => $selectedSettings['sorting'],
            'take' => $selectedSettings['count'],
            'skip' => ($selectedSettings['page'] - 1) * $selectedSettings['count'],
            'where' => ['team' => $companyCode]
        ], 'en');

        $count = app(ObjectManager::class)->count($schema, ['search' => ['team' => $companyCode]]);
        $profilesIds = $members->map(function ($item) {
            return $item->id;
        })->toArray();

        $lecturesMap = [];
        if ($profilesIds) {
            $schema = app(SchemaManager::class)->find('Sections');
            $lectures = app(ObjectManager::class)->allWithLang($schema, [
                'take' => -1,
                'where' => [
                    'userProfileIds' => [
                        '$containsAny' => $profilesIds
                    ]
                ]
            ], 'en');

            foreach ($lectures as $lecture) {
                if (isset($lecture->fields['userProfileIds']) && is_array($lecture->fields['userProfileIds'])) {
                    foreach ($lecture->fields['userProfileIds'] as $profileId) {
                        if (! isset($lecturesMap[$profileId])) {
                            $lecturesMap[$profileId] = [];
                        }
                        $lecturesMap[$profileId][] = $lecture;
                    }
                }
            }
        }

        foreach ($members as $member) {
            $member->fields['lectures'] = $lecturesMap[$member->id] ?? [];

            if (isset($member->fields['status']) && count($member->fields['status'])) {
                $tmp = [];
                foreach ($member->fields['status'] as $k => $statusID) {
                    if (in_array($statusID, self::LECTURE_STATUSES)) {
                        $member->report = true;
                    }
                    foreach ($statuses as $status) {
                        if ($statusID == $status->id) {
                            $tmp[] = $status->fields['Title'];
                        }
                    }
                }
                $member->fields['textstatus'] = ! empty($tmp) ? implode(', ', $tmp) : '';
            }
        }

        $members = new LengthAwarePaginator(
            $members,
            $count,
            $selectedSettings['count'],
            $selectedSettings['page'],
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('form', [
            'lectureStatuses' => self::LECTURE_STATUSES,
            'footballStatuses' => self::FOOTBALL_STATUSES,
            'kvnStatuses' => self::KVN_STATUSES,
            'members' => $members ?? null,
            'statuses' => $statuses,
            'sections' => $sections,
            'companies' => $companies,
            'footballTeams' => $footballTeams,
            'KVNTeams' => $KVNTeams,
            'companyId' => $companyCode,
            'settings' => $this->settings(),
            'selectedSettings' => $selectedSettings
        ]);
    }

    public function ProcessMember(Backend $backend, Request $request, $companyId, $profileId)
    {
        $enFields = $request->only(['en']);
        $fields = $request->except(['subject', 'theses', 'section', 'presentation', 'saved-presentation', '_token', 'en']);
        $lectures = $request->only(['subject', 'theses', 'section', 'presentation', 'saved-presentation']);

        $sections = isset($lectures['section']) && is_array($lectures['section']) ? $lectures['section'] : [];
        foreach ($sections as $k => $section) {
            unset($sections[$k]);
        }

        $fields['phoneNumber'] = str_replace(['(', ')', ' ', '-'], '', $fields['phoneNumber']);
        $fields['team'] = $companyId;
        $fields['groupIds'] = $this->helper->getGroups($fields, $sections, $companyId);
        $fields['tagsIds'] = $this->helper->getTags($fields, $sections, $companyId);
        $fields['sections'] = $sections;

        unset($fields['en']);
        foreach ($enFields as $k => $value) {
            if (! $value) {
                unset($enFields[$k]);
            }
        }

        $schema = app(SchemaManager::class)->find('UserProfiles');

        if ($request->file('photo') && $request->file('photo')->isValid()) {
            $fields['photoFileId'] = $this->helper->uploadPhoto($request->file('photo'));
        }

        app(ObjectManager::class)->save($schema, $profileId, $fields);
        if (count($enFields) && isset($enFields['en'])) {
            app(ObjectManager::class)->save($schema, $profileId, $enFields['en'], 'en');
        }

        $this->processLectures($lectures, $profileId);

        return back();
    }

    public function RemoveMember(Backend $backend, Request $request, $companyId, $profileId)
    {
        $schema = app(SchemaManager::class)->find('UserProfiles');
        $member = app(ObjectManager::class)->find($schema, $profileId);

        if ($member) {
            $userId = $member->fields['userId'] ?? null;
            if (! is_null($userId)) {
                app(\App\Services\UserManager::class)->delete($userId);
            }
            
            $schema = app(SchemaManager::class)->find('UserProfiles');
            app(ObjectManager::class)->delete($schema, $member->id);

            $lecturesSchema = app(SchemaManager::class)->find('Sections');
            $memberLectures = app(ObjectManager::class)->search($lecturesSchema, [
                'take' => -1,
                'where' => [
                    'userProfileIds' => [
                        '$contains' => [$profileId]
                    ]
                ]
            ]);

            if ($memberLectures->count()) {
                foreach ($memberLectures as $lecture) {
                    app(ObjectManager::class)->delete($lecturesSchema, $lecture->id);
                }
            }
        }

        return back();
    }

    private function processLectures(array &$fields, string $member)
    {
        $lectures = [];

        if (isset($fields['theses']['en']) || isset($fields['subject']['en'])) {
            $enData = [
                'theses' => isset($fields['theses']['en']) ? $fields['theses']['en'] : null,
                'subject' => isset($fields['subject']['en']) ? $fields['subject']['en'] : null
            ];
            unset($fields['theses']['en']);
            unset($fields['subject']['en']);
        }

        $emptyList = false;
        foreach ($fields['subject'] as $k => $value) {
            if (
                is_null($fields['subject'][$k]) &&
                is_null($fields['section'][$k]) &&
                is_null($fields['theses'][$k])
            ) {
                unset($fields['subject'][$k]);
                unset($fields['section'][$k]);
                unset($fields['theses'][$k]);
            } else {
                $emptyList = false;
            }
        }

        if ($fields['subject'] && count($fields['subject']) && (!$emptyList)) {
            $schema = app(SchemaManager::class)->find('Sections');

            foreach ($fields['subject'] as $k => $subject) {
                $lecture = [];
                
                if (isset($fields['presentation'][$k]) && $fields['presentation'][$k]->isValid()) {
                    $lecture['presentationFileId'] = $this->helper->uploadPresentation($fields['presentation'][$k]);
                } elseif (isset($fields['saved-presentation'][$k]) && $fields['saved-presentation'][$k]) {
                    $lecture['presentationFileId'] = $fields['saved-presentation'][$k];
                }
                $lecture['title'] = isset($fields['subject'][$k]) ? $fields['subject'][$k] : null;
                $lecture['description'] = isset($fields['theses'][$k]) ? $fields['theses'][$k] : null;
                $lecture['parentId'] = isset($fields['section'][$k]) ? $fields['section'][$k] : null;
                $lecture['groupIds'] = $this->helper->getLectureGroups();
                $lecture['groupTitle'] = self::GROUP_TITLE;

                if (is_numeric($k)) {
                    $lecture['userProfileIds'] = [$member];
                    $lecture = app(ObjectManager::class)->create($schema, $lecture);
                } else {
                    $lecture = app(ObjectManager::class)->save($schema, $k, $lecture);
                }

                if ((isset($enData['theses'][$k]) && $enData['theses'][$k]) || (isset($enData['subject'][$k]) && $enData['subject'][$k])) {
                    $enFields = [
                        'title' => isset($enData['subject'][$k]) ? $enData['subject'][$k] : null,
                        'description' => isset($enData['theses'][$k]) ? $enData['theses'][$k] : null,
                        'groupTitle' => self::GROUP_TITLE_EN
                    ];
                    $lecture = app(ObjectManager::class)->save($schema, $lecture->id, $enFields, 'en');
                }

                $lectures[] = $lecture->id;
            }
        }

        return $lectures;
    }

    public function NewMember(Backend $backend, Request $request, $companyId)
    {
        $enFields = $request->only(['en']);
        $fields = $request->except(['subject', 'theses', 'section', 'presentation', 'saved-presentation', '_token', 'en']);
        $lectures = $request->only(['subject', 'theses', 'section', 'presentation', 'saved-presentation']);

        $sections = isset($lectures['section']) && is_array($lectures['section']) ? $lectures['section'] : [];
        foreach ($sections as $k => $section) {
            unset($sections[$k]);
        }

        foreach ($enFields as $k => $value) {
            if (! $value) {
                unset($enFields[$k]);
            }
        }

        $userProfileSchema = app(SchemaManager::class)->find('UserProfiles');

        do {
            $duplicate = false;
            $login = $this->helper->getRandomPassword();
            try {
                $user = app(\App\Services\UserManager::class)->create([
                    'username' => $login,
                    'password' => $login,
                    'roleId' => self::NEW_USER_ROLE
                ]);
            } catch (ClientException $e) {
                $duplicate = true;
            }
        } while ($duplicate);

        $fields['userId'] = $user->id;
        $fields['code'] = $login;
        $fields['groupIds'] = $this->helper->getGroups($fields, $sections, $companyId);
        $fields['tagsIds'] = $this->helper->getTags($fields, $sections, $companyId);
        $fields['sections'] = $sections;
        $fields['team'] = $companyId;
        $fields['phoneNumber'] = str_replace(['(', ')', ' ', '-'], '', $fields['phoneNumber']);

        if ($request->file('photo') && $request->file('photo')->isValid()) {
            $fields['photoFileId'] = $this->helper->uploadPhoto($request->file('photo'));
        }

        $member = app(ObjectManager::class)->create($userProfileSchema, $fields);

        if (count($enFields) && isset($enFields['en'])) {
            app(ObjectManager::class)->save($userProfileSchema, $member->id, $enFields['en'], 'en');
        }

        $this->processLectures($lectures, $member->id);

        return back();
    }
}
