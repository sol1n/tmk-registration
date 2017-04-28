<?php

namespace App\Services;

use App\User;
use App\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Exceptions\Role\RoleNotFoundException;

class RoleManager
{
    private $list;
    private $token;

    const CACHE_ID = 'roles';
    const CACHE_LIFETIME = 30;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();

        if (! $this->list = $this->getFromCache()) {
            $this->list = Role::list($this->token);
            $this->saveToCache($this->list);
        }
    }

    private function getFromCache()
    {
        if (Cache::has(self::CACHE_ID)) {
            return Cache::get(self::CACHE_ID);
        } else {
            return null;
        }
    }

    private function saveToCache(Collection $data)
    {
        Cache::put(self::CACHE_ID, $data, self::CACHE_LIFETIME);
    }

    public function find(String $id): Role
    {
        foreach ($this->list as $role) {
            if ($role->id == $id) {
                return $role;
            }
        }

        return Role::get($id, $this->token);
    }

    public function all(): Collection
    {
        return $this->list;
    }
}
