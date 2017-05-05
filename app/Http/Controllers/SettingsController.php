<?php

namespace App\Http\Controllers;

use App\Settings;
use App\Language;
use Illuminate\Http\Request;
use App\Services\SchemaManager;
use App\Services\RoleManager;

class SettingsController extends Controller
{
    public function ShowSettingsForm(SchemaManager $schemas, RoleManager $roles)
    {
        $profileFields = [];
        foreach ($schemas->all() as $schema)
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
        'languages' => Language::list(),
        'roles' => $roles->all(),
        'profileFields' => $profileFields,
        'selected' => 'settings'
      ]);
    }

    public function SaveSettings(Settings $settings, Request $request)
    {
        $fields = $request->except(["_token", "action"]);
        $fields['emailSettings']['ssl'] = isset($fields['emailSettings']['ssl']) && $fields['emailSettings']['ssl'] == 'on';

        $settings->save($fields);

        return back();
    }
}
