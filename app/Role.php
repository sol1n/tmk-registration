<?php

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\Role\RoleGetListException;
use Illuminate\Support\Collection;

class Role
{
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

    private function build(Array $data): Role
    {
        $this->id = $data['id'];
        $this->baseRoleId = $data['baseRoleId'];
        $this->rights = $data['rights'];
        $this->createdAt = new Carbon($data['createdAt']);
        $this->updatedAt = new Carbon($data['updatedAt']);
        $this->isDeleted = $data['isDeleted'];

        return $this;
    }

    public static function list(String $token): Collection
    {
        $result = new Collection;

        foreach (static::fetch($token) as $raw) {
            $role = new Role();
            $result->push($role->build($raw));
        }

        return $result;
    }
}
