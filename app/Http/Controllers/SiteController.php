<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\User\WrongCredentialsException;
use GuzzleHttp\Exception\ClientException;

use App\Services\TmkHelper;

use App\Services\ObjectManager;
use App\Services\SchemaManager;

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

    public function ShowEditForm(Backend $backend, $companyCode = null)
    {
        $user = $this->helper->getCurrentUser();

        $statuses = $this->helper->getStatuses();
        $sections = $this->helper->getSections();
        $KVNTeams = $this->helper->getKVNTeams();
        $footballTeams = $this->helper->getFootballTeams();
        $companies = $this->helper->getCompanies($user, $companyCode);
        
        $schema = app(SchemaManager::class)->find('Lectures');
        $lectures = app(ObjectManager::class)->search($schema, ['take' => -1]);
        
        if (is_null($companyCode)) {
            $company = null;
            $team = null;
        } else {
            $schema = app(SchemaManager::class)->find('UserProfiles');
            $members = app(ObjectManager::class)->search($schema, [
                'order' => 'lastName',
                'take' => -1,
                'where' => ['team' => $companyCode]
            ]);
            
            $team = [];
            foreach ($members as $member) {
                if (isset($member->fields['lectures']) && count($member->fields['lectures'])) {
                    foreach ($member->fields['lectures'] as $k => $lectureID) {
                        foreach ($lectures as $lecture) {
                            if ($lectureID == $lecture->id) {
                                $member->fields['lectures'][$k] = $lecture;
                            }
                        }
                    }
                }

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

                $team[] = $member;
            }
        }

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
            'companyId' => $companyCode
        ]);
    }

    public function ProcessMember(Backend $backend, Request $request, $companyId, $profileId)
    {
        $fields = $request->all();
        $fields['email'] = '';
        $fields['photo'] = '';
        $fields['phoneNumber'] = str_replace(['(', ')', ' ', '-'], '', $fields['phoneNumber']);
        $fields['team'] = $companyId;
        unset($fields['_token']);

        $sections = $this->prepareLectures($fields);

        $fields['groupIds'] = $$this->helper->getGroups($fields, $sections, $companyId);
        $fields['sections'] = $sections;

        $enFields = $fields['en'] ?? [];
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
        if (count($enFields)) {
            app(ObjectManager::class)->save($schema, $profileId, $enFields, 'en');
        }

        return redirect()->route('company', ['company' => $companyId]);
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
            if (isset($member->fields['lectures']) and is_array($member->fields['lectures'])) {
                foreach ($member->fields['lectures'] as $lectureId) {
                    $schema = app(SchemaManager::class)->find('Lectures');
                    app(ObjectManager::class)->delete($schema, $lectureId);
                }
            }
            $schema = app(SchemaManager::class)->find('UserProfiles');
            app(ObjectManager::class)->delete($schema, $member->id);
        }

        return redirect()->route('company', ['company' => $companyId]);
    }

    private function prepareLectures(array &$fields)
    {
        $sections = [];

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
            $lectures = [];
            $schema = app(SchemaManager::class)->find('Lectures');

            foreach ($fields['subject'] as $k => $subject) {
                $lecture = [];
                
                if (isset($fields['presentation'][$k]) && $fields['presentation'][$k]->isValid()) {
                    $lecture['presentationFileId'] = $this->helper->uploadPresentation($fields['presentation'][$k]);
                } elseif (isset($fields['saved-presentation'][$k]) && $fields['saved-presentation'][$k]) {
                    $lecture['presentationFileId'] = $fields['saved-presentation'][$k];
                }
                $lecture['Title'] = $fields['subject'][$k];
                $lecture['Description'] = $fields['theses'][$k];
                $lecture['Section'] = $fields['section'][$k];

                $sections[] = $fields['section'][$k];

                if (is_numeric($k)) {
                    $lecture = app(ObjectManager::class)->create($schema, $lecture);
                } else {
                    $lecture = app(ObjectManager::class)->save($schema, $k, $lecture);
                }

                if ((isset($enData['theses'][$k]) && $enData['theses'][$k]) || (isset($enData['subject'][$k]) && $enData['subject'][$k])) {
                    $enFields = [
                        'Title' => isset($enData['subject'][$k]) ? $enData['subject'][$k] : null,
                        'Description' => isset($enData['theses'][$k]) ? $enData['theses'][$k] : null
                    ];
                    $lecture = app(ObjectManager::class)->save($schema, $lecture->id, $enFields, 'en');
                }

                $lectures[] = $lecture->id;
            }

            unset($fields['subject']);
            unset($fields['theses']);
            unset($fields['section']);
            unset($fields['presentation']);
            unset($fields['saved-presentation']);

            $fields['lectures'] = $lectures;
        }

        return $sections;
    }

    public function NewMember(Backend $backend, Request $request, $companyId)
    {
        $fields = $request->all();
        $fields['team'] = $companyId;
        $fields['phoneNumber'] = str_replace(['(', ')', ' ', '-'], '', $fields['phoneNumber']);

        unset($fields['_token']);

        $sections = $this->prepareLectures($fields);

        $enFields = $fields['en'] ?? [];
        unset($fields['en']);

        foreach ($enFields as $k => $value) {
            if (! $value) {
                unset($enFields[$k]);
            }
        }

        if ($request->file('photo') && $request->file('photo')->isValid()) {
            $fields['photoFileId'] = $this->helper->uploadPhoto($request->file('photo'));
        }

        $schema = app(SchemaManager::class)->find('UserProfiles');

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
        $fields['sections'] = $sections;

        $member = app(ObjectManager::class)->create($schema, $fields);

        if (count($enFields)) {
            app(ObjectManager::class)->save($schema, $member->id, $enFields, 'en');
        }

        return redirect()->route('company', ['company' => $companyId]);
    }
}
