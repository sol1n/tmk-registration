<?php

namespace App;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\UnAuthorizedException;
use App\Exceptions\WrongCredentialsException;

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

    public static function login($credentials): User
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

        request()->session()->put('session-token', $json['sessionId']);
        request()->session()->put('refresh-token', $json['refreshToken']);

        return new static();
    }
}
