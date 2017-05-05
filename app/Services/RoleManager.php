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

    public function delete(String $id): Role
    {
        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        if ($index === false)
        {
            throw new RoleNotFoundException;
        }

        $role = $this->list->get($index)->delete($this->token);

        $this->list->forget($index);
        $this->saveToCache($this->list);
        
        return $role;
    }

    public function create(Array $fields): Role
    {
        $role = Role::create($fields, $this->token);
        $this->list->push($role);
        $this->saveToCache($this->list);

        return $role;
    }

    public function save(String $id, Array $fields): Role
    {
        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        if ($index === false)
        {
            throw new RoleNotFoundException;
        }

        $role = $this->list->get($index);
        $role = $role->save($fields, $this->token);
        $this->list->put($index, $role);

        $this->saveToCache($this->list);

        return $role;
    }
}
