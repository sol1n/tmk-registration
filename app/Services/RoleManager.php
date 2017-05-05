<?php

namespace App\Services;

use App\User;
use App\Role;
use App\Traits\Services\CacheableList;

class RoleManager
{
    use CacheableList;

    private $token;

    protected $model = Role::class;
    protected $cacheLifetime = 5;

    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();
        $this->initList();
    }
}
