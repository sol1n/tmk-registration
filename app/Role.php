<?php

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\Role\RoleGetException;
use App\Exceptions\Role\RoleGetListException;
use Illuminate\Support\Collection;

class Role
{
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
            throw new RoleGetException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        if ($data == null)
        {
            throw new RoleGetException;
        }

        return new Role($data);
    }

    public static function list(String $token): Collection
    {
        $result = new Collection;

        foreach (static::fetch($token) as $raw) {
            $role = new Role($raw);
        }

        return $result;
    }
}
