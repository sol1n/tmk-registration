<?php

namespace App\Services;

use App\User;
use App\Object;
use App\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SchemaNotFoundException;

class ObjectManager
{
    private $user;

    const CACHE_ID = 'objects';
    const CACHE_LIFETIME = 60;

    public function __construct()
    {
        $this->user = new User;
    }

    private function saveCollectionToCache(Schema $schema, $data)
    {
        $cacheId = self::CACHE_ID . '-' . $schema->id;

        Cache::put($cacheId, $data, self::CACHE_LIFETIME);
    }

    private function getCollectionFromCache(Schema $schema)
    {
        $cacheId = self::CACHE_ID . '-' . $schema->id;

        if (Cache::has($cacheId)) {
            return Cache::get($cacheId);
        } else {
            return null;
        }
    }

    private function fetchCollection(Schema $schema): Collection
    {
        $objects = $this->getCollectionFromCache($schema);
        if (is_null($objects)) {
            $objects = Object::list($schema, $this->user->token());
            $this->saveCollectionToCache($schema, $objects);
        }
        return $objects;
    }

    public function find(Schema $schema, $id): Object
    {
        $objects = $this->fetchCollection($schema);
        return $objects->where('id', $id)->first();
    }

    public function all(Schema $schema): Collection
    {
        $objects = $this->fetchCollection($schema);
        return $objects;
    }

    public function save(Schema $schema, $id, array $fields): Object
    {
        $objects = $this->fetchCollection($schema);

        $index = $objects->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $object = $objects->get($index);
        $object->save($fields, $this->user->token());
        $objects->put($index, $object);

        $this->saveCollectionToCache($schema, $objects);

        return $object;
    }
}
