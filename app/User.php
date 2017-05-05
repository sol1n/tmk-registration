<?php

namespace App;

use App\Settings;
use App\Language;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\User\UnAuthorizedException;
use App\Exceptions\User\WrongCredentialsException;
use App\Exceptions\User\UsersListGetException;
use App\Exceptions\User\UserNotFoundException;
use App\Exceptions\User\UserSaveException;
use App\Exceptions\User\UserCreateException;
use App\Exceptions\User\UserGetProfilesException;
use App\Traits\Controllers\ModelActions;

class User
{
    use ModelActions;

    private $token;
    private $refreshToken;

    protected function baseUrl(): String
    {
        return 'users';
    } 

    public function token(): string
    {
        if ($this->token !== null) {
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

    public function getProfiles(String $token)
    {
        $url = env('APPERCODE_SERVER');
        $client = new Client;

        try {
            $r = $client->get($url . 'users/' . $this->id . '/profiles', [
                'headers' => ['X-Appercode-Session-Token' => $token]]
            );
        } catch (RequestException $e) {
            throw new UserGetProfilesException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $profiles = new Collection;

        if (count($json))
        {
            foreach($json as $profile)
            {
                $schema = app(SchemaManager::Class)->find($profile['schemaId']);
                $object = app(ObjectManager::Class)->find($schema, $profile['itemId']);
                $profiles->put($schema->id, ['object' => $object, 'code' => $schema->id]);
            }
        }


        $profileSchemas = app(Settings::class)->getProfileSchemas();

        foreach ($profileSchemas as $key => $schema)
        {
            $id = $schema->id;
            $index = $profiles->search(function ($item, $key) use ($id) {
                return isset($item['object']) && $item['object']->schema->id == $id;
            });

            if ($index === false)
            {
                $schema->link = explode('.', $key)[1];
                $profiles->put($schema->id, ['schema' => $schema, 'code' => $schema->id]);
            }
        }

        $this->profiles = $profiles->sortBy('code');

        return $this;
    }

    public static function build(Array $data): User
    {
        $user = new self();
        $user->token = null;
        $user->refreshToken = null;

        $user->id = $data['id'];
        $user->username = $data['username'];
        $user->roleId = $data['roleId'];
        $user->isAnonymous = $data['isAnonymous'];
        $user->createdAt = new Carbon($data['createdAt']);
        $user->updatedAt = new Carbon($data['updatedAt']);

        $user->language = null;
        $languages = Language::list();
        foreach ($languages as $long => $short)
        {
            if ($data['language'] == $short)
            {
                $user->language = collect(['short' => $short, 'long' => $long]);
            }
        }

        return $user;
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


        $user = new self();
        $user->id = $json['userId'];
        $user->roleId = $json['roleId'];
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

    public static function list(String $token): Collection
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER')  . 'users/?take=-1', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        }
        catch (RequestException $e) {
            throw new UsersListGetException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        
        $result = new Collection;
        foreach ($json as $raw)
        {
            $result->push(self::build($raw));
        }

        return $result;
    }

    public static function get(String $id, String $token): User
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER')  . 'users/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        }
        catch (RequestException $e) {
            throw new UserNotFoundException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public function save(Array $fields, String $token): User
    {
        $client = new Client;
        try {
            $r = $client->put(env('APPERCODE_SERVER')  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $token],
                'json' => $fields
            ]);
        }
        catch (RequestException $e) {
            throw new UserSaveException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public static function create(Array $fields, String $token): User
    {
        $client = new Client;
        try {
            $r = $client->post(env('APPERCODE_SERVER')  . 'users/', [
                'headers' => ['X-Appercode-Session-Token' => $token],
                'json' => $fields
            ]);
        }
        catch (RequestException $e) {
            throw new UserCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public function delete(String $token): User
    {
        $client = new Client;
        try {
            $r = $client->delete(env('APPERCODE_SERVER')  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $token],
            ]);
        }
        catch (RequestException $e) {
            throw new UserDeleteException;
        };

        return $this;
    }
}
