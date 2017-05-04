<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Exceptions\User\UserNotFoundException;

class UserManager
{
    private $list;
    private $token;

    const CACHE_ID = 'users3';
    const CACHE_LIFETIME = 10;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();

        if (! $this->list = $this->getFromCache()) {
            $this->list = User::list($this->token);
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

    public function all(): Collection
    {
        return $this->list;
    }

    public function find($id): User
    {
        foreach ($this->list as $user) {
            if ($user->id == $id) {
                return $user;
            }
        }
        return User::get($id, $this->token);
    }

    public function save(String $id, Array $fields): User
    {
        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        if ($index === false)
        {
            throw new UserNotFoundException;
        }

        $user = $this->list->get($index);
        $user = $user->save($fields, $this->token);
        $this->list->put($index, $user);

        $this->saveToCache($this->list);

        return $user;
    }

    public function create(Array $fields): User
    {
        $user = User::create($fields, $this->token);
        $this->list->push($user);
        $this->saveToCache($this->list);

        return $user;
    }

    public function delete(String $id): Collection
    {
        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        if ($index === false)
        {
            throw new UserNotFoundException;
        }

        $user = $this->list->get($index);
        $user->delete($this->token);
        $this->list->forget($index);
        $this->saveToCache($this->list);

        return $this->list;
    }

    public function findWithProfiles(String $id): User
    {
        return $this->find($id)->getProfiles($this->token);
    }

    public function saveProfiles(String $id, Array $profiles)
    {
        foreach ($profiles as $schemaCode => $objects)
        {
            $schema = app(SchemaManager::Class)->find($schemaCode);
            if (isset($objects['new']))
            {
                $needCreate = false;
                $fields = $objects['new'];
                foreach ($fields as &$field)
                {
                    if ($field != null)
                    {
                        $needCreate = true;
                    }
                }
                if ($needCreate)
                {
                    $fields[$objects['link']] = $id;
                    $object = app(ObjectManager::Class)->create($schema, $fields);
                }
            }
            else
            {
                foreach ($objects as $objectCode => $fields)
                {
                    $object = app(ObjectManager::Class)->find($schema, $objectCode);
                    $object = app(ObjectManager::Class)->save($schema, $object->id, $fields);
                }
            }
        }
    }
}
