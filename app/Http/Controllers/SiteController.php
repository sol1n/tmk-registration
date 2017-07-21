<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\User\WrongCredentialsException;

class SiteController extends Controller
{
    public function ShowAuthForm(Backend $backend)
    {
        if ($backend->token)
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

    public function ShowEditForm(Backend $backend)
    {
        
        if (!$backend->token)
        {
            return redirect('/');
        }
        else
        {
            return view('site/form');
        }
    }

    public function ProcessLogin(Backend $backend, Request $request)
    {
        try {
            User::login($backend, $request->all());
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Wrong сredentials data');
            return redirect('/' . $backend->code . '/login/');
        }

        return redirect($backend->code . '/');
    }
}
