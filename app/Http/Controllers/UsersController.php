<?php

namespace App\Http\Controllers;

use App\Backend;
use App\Language;
use App\Services\UserManager;
use App\Services\RoleManager;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function ShowList(UserManager $manager)
    {
        return view('users/list', [
        'users' => $manager->allWithProfiles(),
        'languages' => Language::list(),
        'selected' => 'users'
      ]);
    }

    public function ShowForm(UserManager $manager, RoleManager $roles, Backend $backend, $id)
    {
        return view('users/form', [
        'user' => $manager->findWithProfiles($id),
        'roles' => $roles->all(),
        'languages' => Language::list(),
        'selected' => 'users'
      ]);
    }

    public function SaveUser(Request $request, UserManager $manager, Backend $backend, $id)
    {
        $fields = $request->except(['_token', 'action', 'profiles']);
        $profiles = $request->input('profiles');

        $manager->saveProfiles($id, $profiles);

        if (empty($fields['password'])) {
            unset($fields['password']);
        }

        return $manager->save($id, $fields)->httpResponse();
    }

    public function ShowCreateForm(RoleManager $manager)
    {
        return view('users/create', [
        'roles' => $manager->all(),
        'languages' => Language::list(),
        'selected' => 'users'
      ]);
    }

    public function CreateUser(Request $request, UserManager $manager)
    {
        $fields = $request->except(['_token', 'action']);
        return $manager->create($fields)->httpResponse();
    }

    public function DeleteUser(UserManager $manager, Backend $backend, $id)
    {
        return $manager->delete($id)->httpResponse('list');
    }
}
