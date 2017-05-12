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
    private $lists;

    protected $model = Object::class;
    protected $cacheLifetime = 10;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();
        $this->lists = new Collection;
    }

    private function initList(Schema $schema)
    {
        if (! $this->lists->has($schema->id))
        {
            if (! $objects = $this->getFromCache($schema)) 
            {
                $objects = $this->model::list($schema, $this->token);
                $this->saveToCache($schema, $objects);
            }

            $this->lists->put($schema->id, $objects);
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
        $object = $this->lists->get($schema->id)->where('id', $id)->first();
        if (! is_null($object)) {
            return $object;
        } else {
            return $this->model::get($schema, $id, $this->token);
        }
    }

    public function all(Schema $schema): Collection
    {
        $this->initList($schema);
        return $this->lists->get($schema->id);
    }

    public function save(Schema $schema, $id, array $fields): Object
    {
        $this->initList($schema);
        $list = $this->lists->get($schema->id);

        $index = $list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $object = $list->get($index);
        $object->save($fields, $this->token);
        $list->put($index, $object);

        $this->saveToCache($schema, $list);
        $this->lists->put($schema->id, $list);

        return $object;
    }

    public function create(Schema $schema, array $fields): Object
    {
        $object = $this->model::create($schema, $fields, $this->token);
        $this->initList($schema);
        $this->lists->get($schema->id)->push($object);

        $this->saveToCache($schema, $this->lists->get($schema->id));

        return $object;
    }

    public function delete(Schema $schema, $id): Object
    {
        $this->initList($schema);
        $object = $this->find($schema, $id)->delete($this->token);

        $index = $this->lists->get($schema->id)->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $this->lists->get($schema->id)->forget($index);
        $this->saveToCache($schema, $this->lists->get($schema->id));

        return $object;
    }
}
