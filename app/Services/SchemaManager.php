<?php

namespace App\Services;

use App\User;
use App\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class SchemaManager
{
    private $list;
    private $user;

    const CACHE_ID = 'schemas';
    const CACHE_LIFETIME = 20;

    public function __construct()
    {
        $this->user = new User;

        if (! $this->list = $this->getFromCache()) {
            $this->list = Schema::list($this->user->token());
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


    public function find($id): Schema
    {
        foreach ($this->list as $schema) {
            if ($schema->id == $id) {
                return $schema;
            }
        }

        return Schema::get($id, $this->user->token());
    }

    public function all()
    {
        return $this->list;
    }
}
