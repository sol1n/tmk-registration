<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RoleManager;

class RolesController extends Controller
{
    public function ShowList(RoleManager $manager)
    {
        return view('roles/list', [
        'roles' => $manager->all(),
        'selected' => 'roles'
      ]);
    }

    public function ShowForm(RoleManager $manager, $id)
    {
        return view('roles/form', [
        'role' => $manager->find($id),
        'roles' => $manager->all(),
        'selected' => 'roles'
      ]);
    }

    public function ShowCreateForm(RoleManager $manager)
    {
        return view('roles/create', [
        'roles' => $manager->all(),
        'selected' => 'roles'
      ]);
    }

    public function CreateRole(RoleManager $manager, Request $request)
    {
        $fields = $request->except(["_token", "action"]);

        if (isset($fields['rights'])) {
            foreach ($fields['rights'] as $key => $value) {
                $fields['rights'][$key] = $fields['rights'][$key] == true;
            }
        }
        
        return $manager->create($fields)->jsonResponse();
    }

    public function SaveRole(RoleManager $manager, Request $request, $id)
    {
        $fields = $request->except(["_token", "action"]);

        if (isset($fields['rights'])) {
            foreach ($fields['rights'] as $key => $value) {
                $fields['rights'][$key] = $fields['rights'][$key] == true;
            }
        }
        
        return $manager->save($id, $fields)->jsonResponse();
    }

    public function DeleteRole(RoleManager $manager, $id)
    {
        return $manager->delete($id)->httpResponse('list');
    }
}
