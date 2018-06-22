<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\User\WrongCredentialsException;
use GuzzleHttp\Exception\ClientException;

use Illuminate\Support\Facades\Cache;
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
    const CACHE_LIFETIME = 15;

    private $helper;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!app(Backend::Class)->authorized()) {
                return redirect()->route('index');
            }

            $this->helper = new TmkHelper(app(Backend::Class));

            try {
                $schemaManager = app(SchemaManager::Class);
            } catch (\Exception $e) {
                if ($e instanceof ClientException && $e->hasResponse() && $e->getResponse()->getStatusCode() == 401) {
                    User::forgetSession(app(Backend::Class));
                    return redirect()->route('index');
                }
            }

            return $next($request);
        });
    }

    private function getStatuses()
    {
        if (Cache::has('statuses')) {
            return Cache::get('statuses');
        } else {
            $schema = app(SchemaManager::Class)->find('Statuses');
            $statuses = app(ObjectManager::Class)->search($schema, [
                'take' => -1,
                'order' => [
                    'orderIndex' => 'asc'
                ]
            ]);
            Cache::put('statuses', $statuses, self::CACHE_LIFETIME);
            return $statuses;
        }
    }

    private function getSections()
    {
        if (Cache::has('sections')) {
            return Cache::get('sections');
        } else {
            $schema = app(SchemaManager::Class)->find('Sections');
            $sections = app(ObjectManager::Class)->all($schema);
            Cache::put('sections', $sections, self::CACHE_LIFETIME);
            return $sections;
        }
    }

    private function getCompanies($user, $companyCode)
    {
        $key = 'companies-' . $user->id;
        if (Cache::has($key)) {
            return Cache::get($key);
        } else {
            $companies = $this->helper->checkCompanyAvailability($user, $companyCode);
            Cache::put($key, $companies, self::CACHE_LIFETIME);
            return $companies;
        }
    }

    public function ShowEditForm(Backend $backend, $companyCode = null)
    {
        $user = $this->helper->getCurrentUser();

        $companies = $this->getCompanies($user, $companyCode);
        $statuses = $this->getStatuses();
        $sections = $this->getSections();
        
        $schema = app(SchemaManager::Class)->find('Lectures');
        $lectures = app(ObjectManager::Class)->search($schema, ['take' => -1]);
        
        if (is_null($companyCode))
        {
            $company = null;
            $team = null;
        }
        else
        {
            $schema = app(SchemaManager::Class)->find('UserProfiles');
            $members = app(ObjectManager::Class)->search($schema, [
                'order' => 'lastName', 
                'take' => -1, 
                'where' => ['team' => $companyCode]
            ]);
            
            $team = [];
            foreach ($members as $member)
            {
                if (isset($member->fields['lectures']) && count($member->fields['lectures']))
                {
                    foreach ($member->fields['lectures'] as $k => $lectureID)
                    {
                        foreach ($lectures as $lecture)
                        {
                            if ($lectureID == $lecture->id)
                            {
                                $member->fields['lectures'][$k] = $lecture;
                            }
                        }
                    }
                }

                if (isset($member->fields['status']) && count($member->fields['status']))
                {
                    $tmp = [];
                    foreach ($member->fields['status'] as $k => $statusID)
                    {
                        if (in_array($statusID, self::LECTURE_STATUSES)) {
                            $member->report = true;
                        }
                        foreach ($statuses as $status)
                        {
                            if ($statusID == $status->id)
                            {
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
            'members' => $members ?? null,
            'statuses' => $statuses,
            'sections' => $sections,
            'companies' => $companies,
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

        $fieldsSections = $this->prepareLectures($fields);

        $companySchema = app(SchemaManager::Class)->find('Companies');
        $company = app(ObjectManager::Class)->find($companySchema, $companyId);

        $memberGroups = [];
        if (isset($company->fields['groupId']) && $company->fields['groupId']) {
            $memberGroups[] = $company->fields['groupId'];
        }

        if (isset($fields['status']) && $fields['status']) {
            $memberStatuses = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('Statuses'), [
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
                        $memberGroups[] = $status->fields['groupId'];
                    }
                }
            }
        }

        if (count($fieldsSections) > 0 ) {
            $memberSections = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('Sections'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $fieldsSections
                    ]
                ]
            ]);

            if ($memberSections->isNotEmpty()) {
                foreach ($memberSections as $section) {
                    if (isset($section->fields['groupId']) && $section->fields['groupId']) {
                        $memberGroups[] = $section->fields['groupId'];
                    }
                }
            }
        }

        $fields['groupIds'] = $memberGroups;
        $fields['sections'] = $fieldsSections;

        $enFields = $fields['en'] ?? [];
        unset($fields['en']);
        foreach ($enFields as $k => $value)
        {
            if (! $value)
            {
                unset($enFields[$k]);
            }
        }

        $schema = app(SchemaManager::Class)->find('UserProfiles');

        if ($request->file('photo') && $request->file('photo')->isValid()) {
            $fields['photoFileId'] = $this->helper->uploadPhoto($request->file('photo'));
        }

        app(ObjectManager::Class)->save($schema, $profileId, $fields);
        if (count($enFields))
        {
            app(ObjectManager::Class)->save($schema, $profileId, $enFields, 'en');
        }

        return redirect()->route('company', ['company' => $companyId]);
    }

    public function RemoveMember(Backend $backend, Request $request, $companyId, $profileId)
    {

        $schema = app(SchemaManager::Class)->find('UserProfiles');
        $member = app(ObjectManager::Class)->find($schema, $profileId);

        if ($member) {
            $userId = $member->fields['userId'] ?? null;
            if (! is_null($userId)) {
                app(\App\Services\UserManager::Class)->delete($userId);
            }
            if (isset($member->fields['lectures']) and is_array($member->fields['lectures'])) {
                foreach ($member->fields['lectures'] as $lectureId) {
                    $schema = app(SchemaManager::Class)->find('Lectures');
                    app(ObjectManager::Class)->delete($schema, $lectureId);
                }
            }
            $schema = app(SchemaManager::Class)->find('UserProfiles');
            app(ObjectManager::Class)->delete($schema, $member->id);
        }

        return redirect()->route('company', ['company' => $companyId]);
    }

    private function prepareLectures(array &$fields)
    {
        $sections = []; 

        if (isset($fields['theses']['en']) || isset($fields['subject']['en']))
        {
            $enData = [
                'theses' => isset($fields['theses']['en']) ? $fields['theses']['en'] : null,
                'subject' => isset($fields['subject']['en']) ? $fields['subject']['en'] : null
            ];
            unset($fields['theses']['en']);
            unset($fields['subject']['en']);
        }

        $emptyList = false;
        foreach ($fields['subject'] as $k => $value)
        {
            if (
                is_null($fields['subject'][$k]) && 
                is_null($fields['section'][$k]) && 
                is_null($fields['theses'][$k])
            )
            {
                unset($fields['subject'][$k]);
                unset($fields['section'][$k]);
                unset($fields['theses'][$k]);
            }
            else
            {
                $emptyList = false;
            }
        }

        if ($fields['subject'] && count($fields['subject']) && (!$emptyList))
        {
            $lectures = [];
            $schema = app(SchemaManager::Class)->find('Lectures');

            foreach ($fields['subject'] as $k => $subject)
            {
                $lecture = [];
                
                if (isset($fields['presentation'][$k]) && $fields['presentation'][$k]->isValid())
                {
                    $lecture['presentationFileId'] = $this->helper->uploadPresentation($fields['presentation'][$k]);
                }
                elseif (isset($fields['saved-presentation'][$k]) && $fields['saved-presentation'][$k])
                {
                    $lecture['presentationFileId'] = $fields['saved-presentation'][$k];
                }
                $lecture['Title'] = $fields['subject'][$k];
                $lecture['Description'] = $fields['theses'][$k];
                $lecture['Section'] = $fields['section'][$k];

                $sections[] = $fields['section'][$k];

                if (is_numeric($k))
                {
                    $lecture = app(ObjectManager::Class)->create($schema, $lecture);
                }
                else
                {
                    $lecture = app(ObjectManager::Class)->save($schema, $k, $lecture);
                }

                if ((isset($enData['theses'][$k]) && $enData['theses'][$k]) || (isset($enData['subject'][$k]) && $enData['subject'][$k]))
                {
                    $enFields = [
                        'Title' => isset($enData['subject'][$k]) ? $enData['subject'][$k] : null,
                        'Description' => isset($enData['theses'][$k]) ? $enData['theses'][$k] : null
                    ];
                    $lecture = app(ObjectManager::Class)->save($schema, $lecture->id, $enFields, 'en');
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

        $fieldsSections = $this->prepareLectures($fields);

        $enFields = $fields['en'] ?? [];
        unset($fields['en']);

        foreach ($enFields as $k => $value)
        {
            if (! $value)
            {
                unset($enFields[$k]);
            }
        }

        if ($request->file('photo') && $request->file('photo')->isValid()) {
            $fields['photoFileId'] = $this->helper->uploadPhoto($request->file('photo'));
        }

        $schema = app(SchemaManager::Class)->find('UserProfiles');

        $companySchema = app(SchemaManager::Class)->find('Companies');
        $company = app(ObjectManager::Class)->find($companySchema, $companyId);

        $memberGroups = [];
        if (isset($company->fields['groupId']) && $company->fields['groupId']) {
            $memberGroups[] = $company->fields['groupId'];
        }

        if (isset($fields['status']) && $fields['status']) {
            $memberStatuses = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('Statuses'), [
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
                        $memberGroups[] = $status->fields['groupId'];
                    }
                }
            }
        }

        if (count($fieldsSections) > 0 ) {
            $memberSections = app(ObjectManager::Class)->search(app(SchemaManager::Class)->find('Sections'), [
                'take' => -1,
                'where' => [
                    'id' => [
                        '$in' => $fieldsSections
                    ]
                ]
            ]);

            if ($memberSections->isNotEmpty()) {
                foreach ($memberSections as $section) {
                    if (isset($section->fields['groupId']) && $section->fields['groupId']) {
                        $memberGroups[] = $section->fields['groupId'];
                    }
                }
            }
        }

        $role = $company->fields['roleId'] ?? self::NEW_USER_ROLE;

        do {
            $duplicate = false;
            $login = $this->helper->getRandomPassword();
            try {
                $user = app(\App\Services\UserManager::Class)->create([
                    'username' => $login,
                    'password' => $login,
                    'roleId' => $role
                ]);
            } catch(ClientException $e) {
                $duplicate = true;
            }
        } while($duplicate);

        $fields['userId'] = $user->id;
        $fields['code'] = $login;
        $fields['groupIds'] = $memberGroups;
        $fields['sections'] = $fieldsSections;

        $member = app(ObjectManager::Class)->create($schema, $fields);

        if (count($enFields))
        {
            app(ObjectManager::Class)->save($schema, $member->id, $enFields, 'en');
        }

        return redirect()->route('company', ['company' => $companyId]);
    }
}
