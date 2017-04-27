<?php

namespace App\Http\Controllers;

use App\Role;
use App\Settings;
use App\Language;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function ShowSettingsForm()
    {
        $languages = Language::list();
        $roles = Role::list(request()->user->token());

        $profileFields = [];
        foreach (app(\App\Services\SchemaManager::Class)->all() as $schema)
        {
            foreach ($schema->fields as $field)
            {
                if ($field["type"] == 'ref Users')
                {
                    $profileFields[] = $schema->id . '.' . $field['name'];
                }
            }
        }

        return view('settings/form', [
        'languages' => $languages,
        'roles' => $roles,
        'profileFields' => $profileFields,
        'selected' => 'settings'
      ]);
    }

    public function SaveSettings(Request $request)
    {
        $fields = $request->except(["_token", "action"]);
        $fields['emailSettings']['ssl'] = isset($fields['emailSettings']['ssl']) && $fields['emailSettings']['ssl'] == 'on';

        app(Settings::Class)->save($fields);

        return redirect('/settings/');
    }
}
