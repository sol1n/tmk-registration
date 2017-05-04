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
        'roles' => app(RoleManager::Class)->all(),
        'selected' => 'roles'
      ]);
    }

    public function ShowCreateForm()
    {
        return view('roles/create', [
        'roles' => app(RoleManager::Class)->all(),
        'selected' => 'roles'
      ]);
    }

    public function DeleteRole($roleCode)
    {
        app(RoleManager::Class)->delete($roleCode);
        return redirect('/roles/');
    }

    public function CreateRole(Request $request)
    {
        $fields = $request->except(["_token", "action"]);

        if (isset($fields['rights']))
        {
            foreach ($fields['rights'] as $key => $value)
            {
                $fields['rights'][$key] = $fields['rights'][$key] == true;
            }
        }
        
        $role = app(RoleManager::Class)->create($fields);
        if ($request->input('action') == 'save')
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/roles/']);  
        }
        else
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/roles/' . $role->id . '/']);
        }
        
    }

    public function SaveRole(Request $request, $roleCode)
    {
        $fields = $request->except(["_token", "action"]);

        if (isset($fields['rights']))
        {
            foreach ($fields['rights'] as $key => $value)
            {
                $fields['rights'][$key] = $fields['rights'][$key] == true;
            }
        }
        
        $role = app(RoleManager::Class)->save($roleCode, $fields);
        if ($request->input('action') == 'save')
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/roles/']);  
        }
        else
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/roles/' . $role->id . '/']);
        }
        
    }
}
