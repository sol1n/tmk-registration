<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function ShowList()
    {
        return view('roles/list', [
        'selected' => 'roles'
      ]);
    }


}
