<?php

namespace App\Services;

use App\User;
use App\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class SchemaManager
{
    private $list;
    private $token;

    const CACHE_ID = 'schemas';
    const CACHE_LIFETIME = 20;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();

        if (! $this->list = $this->getFromCache()) {
            $this->list = Schema::list($this->token);
            $this->saveToCache($this->list);
        }
    }

    public static function fieldTypes(): Array
    {
        return [
            'Integer', 'Double', 'Money', 'DateTime', 'Boolean', 'String', 'Text', 'Uuid', 'Json', 'ref Users'
        ];
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

        return Schema::get($id, $this->token);
    }

    public function save(String $id, Array $fields): Schema
    {
        $schema = $this->find($id);
        $schema = $schema->save($fields, $this->token);

        $index = $this->list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $this->list->put($index, $schema);
        $this->saveToCache($this->list);

        return $schema;
    }

    public function all()
    {
        return $this->list;
    }
}
