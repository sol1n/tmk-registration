<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function ShowList()
    {
        return view('users/list', [
        'selected' => 'users'
      ]);
    }


}
