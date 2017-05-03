<?php

namespace App\Http\Controllers;

use App\User;
use App\Language;
use App\Services\UserManager;
use App\Services\RoleManager;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function ShowList()
    {
        return view('users/list', [
        'users' => app(UserManager::Class)->all(),
        'languages' => Language::list(),
        'selected' => 'users'
      ]);
    }

    public function ShowForm($userID)
    {
      return view('users/form', [
        'user' => app(UserManager::Class)->find($userID),
        'roles' => app(RoleManager::Class)->all(),
        'languages' => Language::list(),
        'selected' => 'users'
      ]);
    }

    public function SaveUser(Request $request, $userID)
    {
      $fields = $request->except(['_token', 'action']);
      if (empty($fields['password']))
      {
        unset($fields['password']);
      }

      $user = app(UserManager::Class)->save($userID, $fields);

      if ($request->input('action') == 'save')
      {
        return redirect('/users/');
      }
      else
      {
        return redirect('/users/' . $user->id . '/');
      }
    }

    public function ShowCreateForm()
    {
      return view('users/create', [
        'roles' => app(RoleManager::Class)->all(),
        'languages' => Language::list(),
        'selected' => 'users'
      ]);
    }

    public function CreateUser(Request $request)
    {
      $fields = $request->except(['_token', 'action']);
      $user = app(UserManager::Class)->create($fields);

      if ($request->input('action') == 'save')
      {
        return redirect('/users/');
      }
      else
      {
        return redirect('/users/' . $user->id . '/');
      }
    }

    public function DeleteUser($userID)
    {
      $user = app(UserManager::Class)->delete($userID);
      return redirect('/users/');
    }
}
