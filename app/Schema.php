<?php

namespace App;

use Illuminate\Support\Collection;

class Schema
{
    public $id;
    public $title;

    public static function build($data)
    {
        $schema = new static();
        $schema->id = $data['id'];
        $schema->title = $data['title'] ? $data['title'] : $data['id'];
        $schema->fields = $data['fields'];

        return $schema;
    }
}
