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

    public static function fieldTypes(): array
    {
        return [
            'Integer', 'Double', 'Money', 'DateTime', 'Boolean', 'String', 'Text', 'Uuid', 'Json', 'ref Users'
        ];
    }
}
