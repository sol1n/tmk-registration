<?php

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\Role\RoleNotFoundException;
use App\Exceptions\Role\RoleGetListException;
use App\Exceptions\Role\RoleCreateException;
use App\Exceptions\Role\RoleSaveException;
use Illuminate\Support\Collection;
use App\Traits\Controllers\ModelActions;

class Role
{
    use ModelActions;

    protected function baseUrl(): String
    {
        return 'roles';
    } 

    private function __construct(Array $data)
    {
        $this->id = $data['id'];
        $this->baseRoleId = $data['baseRoleId'];
        $this->rights = $data['rights'];
        $this->createdAt = new Carbon($data['createdAt']);
        $this->updatedAt = new Carbon($data['updatedAt']);
        $this->isDeleted = $data['isDeleted'];

        return $this;
    }

    private static function fetch(String $token): Array
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER') . 'roles', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new RoleGetListException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        return $data;
    }

    public static function get(String $id, String $token): Role
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER') . 'roles/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new RoleNotFoundException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        return new Role($data);
    }

    public static function list(String $token): Collection
    {
        $result = new Collection;

        foreach (static::fetch($token) as $raw) {
            $result->push(new Role($raw));
        }

        return $result;
    }

    public function delete(String $token): Role
    {
        $client = new Client;
        try {
            $r = $client->delete(env('APPERCODE_SERVER')  . 'roles/' . $this->id, ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new RoleDeleteException;
        };

        return $this;
    }

    public static function create(Array $fields, String $token): Role
    {
        $client = new Client;
        try {
            $r = $client->post(env('APPERCODE_SERVER')  . 'roles', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ], 'json' => $fields]);
        } catch (RequestException $e) {
            throw new RoleCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return new Role($json);
    }

    public function save(Array $fields, String $token): Role
    {
        $fields['id'] = $this->id;

        $client = new Client;
        try {
            $r = $client->put(env('APPERCODE_SERVER')  . 'roles', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ], 'json' => $fields]);
        } catch (RequestException $e) {
            throw new RoleSaveException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        
        return new Role($json);
    }
}
