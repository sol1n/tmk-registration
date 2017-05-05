<?php

namespace App\Services;

use App\User;
use App\Object;
use App\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ObjectManager
{
    private $token;
    private $list;

    protected $model = Object::class;
    protected $cacheLifetime = 10;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();
    }

    private function initList(Schema $schema)
    {
        if (! $this->list = $this->getFromCache($schema)) {
            $this->list = $this->model::list($schema, $this->token);
            $this->saveToCache($schema, $this->list);
        }
    }

    private function getCacheTag(Schema $schema): String
    {
        return $this->model . '-' . $schema->id;
    }

    private function saveToCache(Schema $schema, Collection $data)
    {
        if (env('APPERCODE_ENABLE_CACHING') == 1)
        {
            Cache::put($this->getCacheTag($schema), $data, $this->cacheLifetime);
        }
    }

    private function getFromCache(Schema $schema)
    {
        if (Cache::has($this->getCacheTag($schema)) && (env('APPERCODE_ENABLE_CACHING') == 1)) {
            return Cache::get($this->getCacheTag($schema));
        } else {
            return null;
        }
    }

    public function find(Schema $schema, $id): Object
    {
        $this->initList($schema);
        $object = $this->list->where('id', $id)->first();
        if (! is_null($object)) {
            return $object;
        } else {
            return $this->model::get($schema, $id, $this->token);
        }
    }

    public function all(Schema $schema): Collection
    {
        $this->initList($schema);
        return $this->list;
    }

    public function save(Schema $schema, $id, array $fields): Object
    {
        $this->initList($schema);

        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $object = $this->list->get($index);
        $object->save($fields, $this->token);
        $this->list->put($index, $object);

        $this->saveToCache($schema, $this->list);

        return $object;
    }

    public function create(Schema $schema, array $fields): Object
    {
        $object = $this->model::create($schema, $fields, $this->token);
        $this->initList($schema);
        $this->list->push($object);

        $this->saveToCache($schema, $this->list);

        return $object;
    }

    public function delete(Schema $schema, $id): Object
    {
        $this->initList($schema);
        $object = $this->find($schema, $id)->delete($this->token);

        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $this->list->forget($index);
        $this->saveToCache($schema, $this->list);

        return $object;
    }
}
