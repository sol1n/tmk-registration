<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\User\WrongCredentialsException;

class SiteController extends Controller
{

    public function ShowAuthForm(Backend $backend, Request $request)
    {
        if (isset($backend->token))
        {
            return redirect('/form/');
        }
        else
        {
            return view('site/login', [
            'message' => session('login-error')
          ]);
        }
    }

    public function ShowEditForm(Backend $backend, $companyCode = null)
    {
        
        if (!isset($backend->token))
        {
            return redirect('/');
        }
        else
        {
            $user = app(\App\Services\UserManager::Class)->findWithProfiles(session('tmk-id'));

            if (isset($user->profiles['UserProfiles']['object']->fields['companies']))
            {
                $userCompanies = $user->profiles['UserProfiles']['object']->fields['companies'];
            }
            else
            {
                throw new \Exception('Empty companies list');
            }
            
            if ($companyCode)
            {
                foreach ($userCompanies as $one)
                {
                    if ($one->id == $companyCode)
                    {
                        $company = $one;
                    }
                }
            }

            $schema = app(\App\Services\SchemaManager::Class)->find('Statuses');
            $statuses = app(\App\Services\ObjectManager::Class)->all($schema);

            $schema = app(\App\Services\SchemaManager::Class)->find('Sections');
            $sections = app(\App\Services\ObjectManager::Class)->all($schema);

            $schema = app(\App\Services\SchemaManager::Class)->find('KVNTeams');
            $kvnTeams = app(\App\Services\ObjectManager::Class)->all($schema);

            $schema = app(\App\Services\SchemaManager::Class)->find('footballTeam');
            $footballTeams = app(\App\Services\ObjectManager::Class)->all($schema);

            $schema = app(\App\Services\SchemaManager::Class)->find('Lectures');
            $lectures = app(\App\Services\ObjectManager::Class)->all($schema);
            
            if (! isset($company))
            {
                $company = null;
                $team = null;
            }
            else
            {

                $schema = app(\App\Services\SchemaManager::Class)->find('UserProfiles');
                $members = app(\App\Services\ObjectManager::Class)->allWithLang($schema, ['where' => json_encode(['team' => $company->id])], 'en');

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

                    if (isset($member->fields['team']) && $member->fields['team'] == $company->id)
                    {
                        $tmp = [];

                        foreach ($statuses as $status)
                        {
                            if (isset($member->fields['status']) && in_array($status->id, $member->fields['status']))
                            {
                                $tmp[] = $status->fields['Title'];
                            }
                        }
                        if (isset($member->fields['status']) &&  in_array('5a4b73ea-dd18-45ed-9523-e24af036bd13', $member->fields['status']))
                        {
                            $member->report = 1;
                        }
                        if (isset($member->fields['status']) &&  in_array('c40c992e-430d-4545-95b1-d63645f8a6fa', $member->fields['status']))
                        {
                            $member->football = 1;
                        }
                        if (isset($member->fields['status']) &&  in_array('0567ede5-b573-4dda-8866-d988d1456f33', $member->fields['status']))
                        {
                            $member->kvn = 1;
                        }

                        $member->fields['textstatus'] = ! empty($tmp) ? implode(', ', $tmp) : '';
                        $team[] = $member;
                    }
                } 
            }

            

            return view('site/form', [
                'user' => $user,
                'company' => $company,
                'companies' => $userCompanies,
                'team' => $team,
                'statuses' => $statuses,
                'sections' => $sections,
                'kvnTeams' => $kvnTeams,
                'footballTeams' => $footballTeams
            ]);
        }
    }

    public function ProcessLogin(Backend $backend, Request $request)
    {
        try {
            User::login($backend, $request->all());
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Некорректные логин или пароль');
            return redirect('/');
        }

        return redirect('/form/');
    }

    public function ProcessMember(Backend $backend, Request $request, $company, $profile)
    {
        $fields = $request->all();
        $fields['email'] = '';
        $fields['photo'] = '';
        $fields['presentation'] = '';
        $fields['team'] = $company;
        unset($fields['_token']);

        $enFields = $fields['en'];
        unset($fields['en']);
        foreach ($enFields as $k => $value)
        {
            if (! $value)
            {
                unset($enFields[$k]);
            }
        }

        $schema = app(\App\Services\SchemaManager::Class)->find('UserProfiles');
        $member = app(\App\Services\ObjectManager::Class)->find($schema, $profile);

        if ($request->file('presentation'))
        {
            $path = $request->presentation->store('presentations');
            $fields['presentation'] = $path;
        }
        else
        {
            $fields['presentation'] = isset($member->fields['presentation']) ? $member->fields['presentation'] : null;
        }

        if ($request->file('photo'))
        {
            $path = $request->photo->store('images');
            $fields['photo'] = $path;
        }
        else
        {
            $fields['photo'] = isset($member->fields['photo']) ? $member->fields['photo'] : null;
        }

        $fields['userId'] = $member->fields['userId'];

        app(\App\Services\ObjectManager::Class)->save($schema, $member->id, $fields);
        if (count($enFields))
        {
            app(\App\Services\ObjectManager::Class)->save($schema, $member->id, $enFields, 'en');
        }

        return redirect('/form/' . $company . '/');
    }

    public function RemoveMember(Backend $backend, Request $request, $company, $profile)
    {

        $schema = app(\App\Services\SchemaManager::Class)->find('UserProfiles');
        $member = app(\App\Services\ObjectManager::Class)->find($schema, $profile);

        $fields = $member->fields;
        

        foreach ($schema->fields as $field)
        {
            $index = $field['name'];
            if ($field['multiple'])
            {
                $fields[$index] = isset($fields[$index]) ? $fields[$index] : [];
            }
            else
            {
                $fields[$index] = isset($fields[$index]) ? $fields[$index] : null;
            }
        }

        $fields['team'] = "00000000-0000-0000-0000-000000000000";

        app(\App\Services\ObjectManager::Class)->save($schema, $member->id, $fields);

        return redirect('/form/' . $company . '/');
    }

    public function NewMember(Backend $backend, Request $request, $company)
    {
        $fields = $request->all();
        $fields['email'] = '';
        $fields['team'] = $company;
        unset($fields['_token']);

        if ($fields['subject'] && $fields['subject'][0])
        {
            $lectures = [];
            $schema = app(\App\Services\SchemaManager::Class)->find('Lectures');

            foreach ($fields['subject'] as $k => $subject)
            {
                $lecture = [];
                if (isset($fields['presentation'][$k]))
                {
                    $lecture['Presentation'] = $fields['presentation'][$k]->store('presentations');
                }
                $lecture['Title'] = $fields['subject'][$k];
                $lecture['Description'] = $fields['theses'][$k];
                $lecture['Section'] = $fields['section'][$k];

                $lecture = app(\App\Services\ObjectManager::Class)->create($schema, $lecture);

                $lectures[] = $lecture->id;

            }

            unset($fields['subject']);
            unset($fields['theses']);
            unset($fields['section']);
            unset($fields['presentations']);

            $fields['lectures'] = $lectures;
        }

        $enFields = $fields['en'];
        unset($fields['en']);

        foreach ($enFields as $k => $value)
        {
            if (! $value)
            {
                unset($enFields[$k]);
            }
        }

        if ($request->file('photo'))
        {
            $path = $request->photo->store('images');
            $fields['photo'] = $path;
        }
        else
        {
            $fields['photo'] = null;
        }

        $schema = app(\App\Services\SchemaManager::Class)->find('UserProfiles');

        $time = time();

        $user = app(\App\Services\UserManager::Class)->create([
            'username' => $time,
            'password' => $time,
            'roleId' => 'Player'
        ]);

        $fields['userId'] = $user->id;

        $member = app(\App\Services\ObjectManager::Class)->create($schema, $fields);

        if (count($enFields))
        {
            app(\App\Services\ObjectManager::Class)->save($schema, $member->id, $enFields, 'en');
        }

        return redirect('/form/' . $company . '/');
    }


    public function ProcessForm(Backend $backend, Request $request, $companyCode)
    {
        $user = app(\App\Services\UserManager::Class)->findWithProfiles(session('tmk-id'));
        if (isset($user->profiles['UserProfiles']['object']->fields['companies']))
        {
            $userCompanies = $user->profiles['UserProfiles']['object']->fields['companies'];
        }
        else
        {
            throw new Exception('Empty companies list');
        }
        
        if ($companyCode)
        {
            foreach ($userCompanies as $one)
            {
                if ($one->id == $companyCode)
                {
                    $company = $one;
                }
            }
        }

        if (! isset($company))
        {
            throw new Exception('No company provided');
        }

        $fields = $request->only(['DefaultFootballTeam', 'DefaultKVNTeam']);
        $fields['Title'] = $company->fields['Title'];

        app(\App\Services\ObjectManager::Class)->save($company->schema, $company->id, $fields);

        return redirect('/form/' . $company->id . '/');
    }
}
