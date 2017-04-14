<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use App\Exceptions\WrongCredentialsException;

class AuthController extends Controller
{
    public function ShowAuthForm()
    {
        return view('auth/login', [
        'message' => session('login-error')
      ]);
    }

    public function ProcessLogin(Request $request)
    {
        try {
            User::login($request);
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Wrong сredentials data');
            return redirect('/login');
        }

        return redirect('/');
    }
}
