<?php

namespace App\Services;

use App\User;
use App\Traits\Services\CacheableList;

class UserManager
{
    use CacheableList;

    private $token;

    protected $model = User::class;
    protected $cacheLifetime = 10;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();
        $this->initList();
    }

    public function findWithProfiles(String $id): User
    {
        return $this->find($id)->getProfiles($this->token);
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
}
