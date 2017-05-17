<?php

namespace App\Services;

use App\User;
use App\Backend;
use Illuminate\Support\Collection;
use App\Traits\Services\CacheableList;

class UserManager
{
    use CacheableList;

    private $token;

    protected $model = User::class;
    protected $cacheLifetime = 10;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
        $this->initList();
    }

    public function findWithProfiles(String $id): User
    {
        return $this->find($id)->getProfiles($this->backend);
    }

    public function allWithProfiles(): Collection
    {
        $elements = new Collection;
        $profileSchemas = app(\App\Settings::class)->getProfileSchemas();
        if ($profileSchemas)
        {
            foreach ($profileSchemas as $key => $schema)
            {
                $elements->put($key, app(ObjectManager::class)->all($schema));
            }

            $this->list = $this->list->each(function($user) use ($elements){
                $user->profiles = new Collection;
                foreach ($elements as $key => $profiles)
                {
                    $fieldName = explode('.', $key)[1];
                    $schemaName = explode('.', $key)[0];
                    $index = $profiles->search(function($profile, $i) use ($fieldName, $user) {
                       return $profile->fields[$fieldName] == $user->id;
                    });

                    if ($index !== false)
                    {
                        $user->profiles->put($schemaName, ['object' => $profiles->get($index)]);
                    }
                }
                
            });
        }
        
        return $this->list;
    }

    public function saveProfiles(String $id, array $profiles)
    {
        $schemaManager = app(SchemaManager::class);
        $objectManager = app(ObjectManager::class);

        foreach ($profiles as $schemaCode => $objects) {
            $schema = $schemaManager->find($schemaCode);
            if (isset($objects['new'])) {
                $needCreate = false;
                $fields = $objects['new'];
                foreach ($fields as &$field) {
                    if ($field != null) {
                        $needCreate = true;
                    }
                }
                if ($needCreate) {
                    $fields[$objects['link']] = $id;
                    $object = $objectManager->create($schema, $fields);
                }
            } else {
                foreach ($objects as $objectCode => $fields) {
                    $object = $objectManager->find($schema, $objectCode);
                    $object = $objectManager->save($schema, $object->id, $fields);
                }
            }
        }
    }

    public function getMappedUsers(String $keyField = 'id', String $valueField = 'username'): Collection
    {
        $result = $this->all()->mapWithKeys(function ($item) use($keyField, $valueField) {
            return [$item->{$keyField} => $item->{$valueField}];
        });
        return $result;
    }
}
