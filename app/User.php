<?php

namespace App;

use App\Backend;
use App\Settings;
use App\Language;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use function GuzzleHttp\Psr7\build_query;
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
        $backend = app(Backend::Class);
        if (session($backend->code . '-session-token')) {
            $this->token = session($backend->code . '-session-token');
        }
        if (session($backend->code . '-refresh-token')) {
            $this->refreshToken = session($backend->code .'-refresh-token');
        }
    }

    public function getProfiles(Backend $backend)
    {
        $client = new Client;

        try {
            $r = $client->get($backend->url . 'users/' . $this->id . '/profiles', [
                'headers' => ['X-Appercode-Session-Token' => $backend->token]]
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
                $schema = app(SchemaManager::Class)->find($profile['schemaId'])->withRelations();
                $object = app(ObjectManager::Class)->find($schema, $profile['itemId']);
                $profiles->put($schema->id, ['object' => $object->withRelations(), 'code' => $schema->id]);
            }
        }

        $profileSchemas = app(Settings::class)->getProfileSchemas();

        if ($profileSchemas) {
            foreach ($profileSchemas as $key => $schema) {
                $id = $schema->id;
                $index = $profiles->search(function ($item, $key) use ($id) {
                    return isset($item['object']) && $item['object']->schema->id == $id;
                });

                if ($index === false) {
                    $schema->link = explode('.', $key)[1];
                    $profiles->put($schema->id, ['schema' => $schema->withRelations(), 'code' => $schema->id]);
                }
            }

            $this->profiles = $profiles->sortBy('code');
        }
        else{
            $this->profiles = $profiles;
        }

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

    public static function login(Backend $backend, Array $credentials, Bool $storeSession = true): User
    {
        $client = new Client;

        try {
            $r = $client->post($backend->url . 'login', ['json' => [
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
            $user->storeSession($backend);
        }

        return $user;
    }

    public function storeSession(Backend $backend): User
    {
        request()->session()->put($backend->code . '-session-token', $this->token);
        request()->session()->put($backend->code . '-refresh-token', $this->refreshToken);
        request()->session()->put($backend->code . '-id', $this->id);
        return $this;
    }

    public function regenerate(Backend $backend, Bool $storeSession = true): User
    {
        $client = new Client;

        try {
            $r = $client->post($backend->url . 'login/byToken', [
                'headers' => ['Content-Type' => 'application/json'], 
                'body' => '"' . $this->refreshToken . '"']
            );
        } catch (RequestException $e) {
            throw new WrongCredentialsException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $this->token = $json['sessionId'];
        $this->refreshToken = $json['refreshToken'];
        $this->id = $json['userId'];

        if ($storeSession)
        {
            $this->storeSession($backend);
        }

        return $this;
    }

    public static function list(Backend $backend, $params = []): Collection
    {
        $client = new Client;
        if (!$params) {
            $params['take'] = -1;
        }
        $getParams = http_build_query($params);
        try {
            $r = $client->get($backend->url  . 'users/?' . $getParams, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
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

    public static function findMultiple(Backend $backend, $params) : Collection
    {

    }

    public static function getUsersAmount($backend) {
        $result = 0;
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'users/?take=1&count=true', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        }
        catch (RequestException $e) {
            throw new UsersListGetException;
        };
        if ($r->getHeader('x-appercode-totalitems')){
            $result = $r->getHeader('x-appercode-totalitems')[0];
        }

        return $result;
    }

    public static function get(String $id, Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'users/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        }
        catch (RequestException $e) {
            throw new UserNotFoundException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public function save(Array $fields, Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->put($backend->url  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'json' => $fields
            ]);
        }
        catch (RequestException $e) {
            throw new UserSaveException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public static function create(Array $fields, Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->post($backend->url  . 'users/', [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'json' => $fields
            ]);
        }
        catch (RequestException $e) {
            throw new UserCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public function delete(Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->delete($backend->url  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
            ]);
        }
        catch (RequestException $e) {
            throw new UserDeleteException;
        };

        return $this;
    }

    public function shortView(): String
    {
        if (isset(app(\App\Settings::Class)->properties['usersShortView']))
        {
            $template = app(\App\Settings::Class)->properties['usersShortView'];
            if (isset($this->profiles) && (!$this->profiles->isEmpty()))
            {
                foreach ($this->profiles as $schema => $profile){
                    if (isset($profile['object']))
                    {
                       foreach ($profile['object']->fields as $code => $value)
                       {
                            if ((is_string($value) || is_numeric($value)) && mb_strpos($template, ":$schema.$code:") !== false)
                            {
                                $template = str_replace(":$schema.$code:", $value, $template);
                            }
                       }
                    }
                }
                $template = str_replace(":id:", $this->id, $template);
                $template = str_replace(":username:", $this->username, $template);
                return $template;
            }
            else
            {
                return $this->username;
            }
        }
        else
        {
            return $this->username;
        }
    }
}
