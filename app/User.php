<?php

namespace App;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\User\UnAuthorizedException;
use App\Exceptions\User\WrongCredentialsException;

class User
{
    private $token;
    private $refreshToken;

    public function token(): string
    {
        if (isset($this->token)) {
            return $this->token;
        } else {
            throw new UnAuthorizedException;
        }
    }

    public function __construct()
    {
        if (session('session-token')) {
            $this->token = session('session-token');
        }
        if (session('refresh-token')) {
            $this->refreshToken = session('refresh-token');
        }
    }

    public static function login(Array $credentials, Bool $storeSession = true): User
    {
        $url = env('APPERCODE_SERVER');
        $client = new Client;

        try {
            $r = $client->post($url . 'login', ['json' => [
              'username' => $credentials['login'],
              'password' => $credentials['password'],
              'installId' => '',
              'generateRefreshToken' => true
            ]]);
        } catch (RequestException $e) {
            throw new WrongCredentialsException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $user = new Static();
        $user->token = $json['sessionId'];
        $user->refreshToken = $json['refreshToken'];

        if ($storeSession)
        {
            $user->storeSession();
        }

        return $user;
    }

    public function storeSession(): User
    {
        request()->session()->put('session-token', $this->token);
        request()->session()->put('refresh-token', $this->refreshToken);
        return $this;
    }

    public function regenerate(Bool $storeSession = true): User
    {
        $url = env('APPERCODE_SERVER');
        $client = new Client;

        try {
            $r = $client->post($url . 'login/byToken', [
                'headers' => ['Content-Type' => 'application/json'], 
                'body' => '"' . $this->refreshToken . '"']
            );
        } catch (RequestException $e) {
            throw new WrongCredentialsException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $this->token = $json['sessionId'];
        $this->refreshToken = $json['refreshToken'];

        if ($storeSession)
        {
            $this->storeSession();
        }

        return $this;
    }
}
