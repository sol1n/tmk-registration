<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RoleManager;

class RolesController extends Controller
{
    public function ShowList()
    {
        return view('roles/list', [
        'roles' => app(RoleManager::Class)->all(),
        'selected' => 'roles'
      ]);
    }

    public function ShowForm($roleCode)
    {
        return view('roles/form', [
        'role' => app(RoleManager::Class)->find($roleCode),
        'selected' => 'roles'
      ]);
    }
}
