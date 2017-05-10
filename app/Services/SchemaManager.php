<?php

namespace App\Services;

use App\User;
use App\Schema;
use App\Traits\Services\CacheableList;

class SchemaManager
{
    use CacheableList;

    private $token;

    protected $model = Schema::class;
    protected $cacheLifetime = 5;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();
        $this->initList();
    }

    public function fieldTypes(): array
    {
        $result = [
            'basic' => ['Integer', 'Double', 'Money', 'DateTime', 'Boolean', 'String', 'Text', 'Uuid', 'Json'],
            'refs' => ['ref Users']
        ];

        foreach ($this->list as $schema)
        {
            $result['refs'][] = 'ref ' . $schema->id;
        }

        return $result;
    }
}
