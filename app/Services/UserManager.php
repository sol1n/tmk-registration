<?php

namespace App\Services;

use App\File;
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

    CONST USERS_PER_PAGE = 100;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
        //$this->initList();
    }

    public function findWithProfiles(String $id): User
    {
        return $this->find($id)->getProfiles($this->backend);
    }

    public function getTotalAmount() {
        return User::getUsersAmount($this->backend);
    }


    public function all($page = -1) {
        $params = [];
        if ($page != -1) {
            $params['take'] = static::USERS_PER_PAGE;
            $params['skip'] = ($page - 1) * static::USERS_PER_PAGE;
        }
        $this->list = User::list($this->backend, $params);
        return $this->list;
    }

    /**
     * Redefine  CacheableList find, don't use cache
     * @param String $id
     * @return mixed
     */
    public function find(String $id)
    {
        $element = $this->model::get($id, $this->backend);
        return $element;
    }

    public function allWithProfiles($page = 1): Collection
    {
        $elements = new Collection;
        $users = $this->all($page);
        $profileSchemas = app(\App\Settings::class)->getProfileSchemas();
        if ($profileSchemas)
        {
            $query = ['where' => json_encode(['user' => ['$in' => $users->pluck('id')]])];
            foreach ($profileSchemas as $key => $schema)
            {
                $elements->put($key, app(ObjectManager::class)->all($schema, $query));
            }

            $users = $users->each(function($user) use ($elements){
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
        
        return $users;
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
