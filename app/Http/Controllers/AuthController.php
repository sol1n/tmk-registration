<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\User\WrongCredentialsException;

class AuthController extends Controller
{
    public function ShowAuthForm(Backend $backend, Request $request)
    {
        if (isset($backend->token))
        {
            return redirect('/form/');
        }
        else
        {
            return view('login');
        }
    }

    public function ProcessLogin(Backend $backend, Request $request)
    {
        try {
            User::login($backend, $request->all());
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Некорректные логин или пароль');
            return redirect('/');
        }

        return redirect('/form/');
    }

    public function ProcessLogout(Backend $backend)
    {
        $backend->logout();
        return redirect('/');
    }
}
