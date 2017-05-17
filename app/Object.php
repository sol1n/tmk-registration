<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use App\Exceptions\Object\ObjectSaveException;
use App\Exceptions\Object\ObjectCreateException;
use App\Exceptions\Object\ObjectNotFoundException;
use App\Traits\Controllers\ModelActions;
use App\Traits\Models\FieldsFormats;

class Object
{
    use ModelActions, FieldsFormats;

    public $fields;
    public $schema;

    protected function baseUrl(): String
    {
        return $this->schema->id;
    }

    public function save($data, Backend $backend): Object
    {
        $this->fields = static::prepareRawData($data, $this->schema);

        $client = new Client;
        try {
            $r = $client->put($backend->url . 'objects/' . $this->schema->id . '/' . $this->id, ['headers' => [
              'X-Appercode-Session-Token' => $backend->token
          ], 'json' => $this->fields]);
        } catch (ServerException $e) {
            throw new ObjectSaveException;
        }

        return $this;
    }

    public static function get(Schema $schema, $id, Backend $backend): Object
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'objects/' . $schema->id . '/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (ClientException $e) {
            throw new ObjectNotFoundException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($schema, $json);
    }

    public static function create(Schema $schema, $fields, Backend $backend): Object
    {
        $fields = self::prepareRawData($fields, $schema);

        $client = new Client;
        try {
            $r = $client->post($backend->url . 'objects/' . $schema->id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $fields]);
        } catch (ServerException $e) {
            throw new ObjectCreateException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($schema, $json);
    }

    public function delete(Backend $backend): Object
    {
        $client = new Client;
        $r = $client->delete($backend->url . 'objects/' . $this->schema->id . '/' . $this->id, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        return $this;
    }

    public static function list(Schema $schema, Backend $backend): Collection
    {
        $list = new Collection;

        $client = new Client;
        $r = $client->get($backend->url . 'objects/' . $schema->id, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);

        foreach ($json as $rawData) {
            $list->push(Object::build($schema, $rawData));
        }

        return $list;
    }

    public static function build(Schema $schema, $data): Object
    {
        $object = new static();
        $object->id = $data['id'];
        $object->createdAt = new Carbon($data['createdAt']);
        $object->updatedAt = new Carbon($data['updatedAt']);
        $object->fields = self::prepareRawData($data, $schema);

        $object->schema = $schema;

        return $object;
    }

    private function getUserRelation($field)
    {
        if (! isset($this->relations['ref Users']))
        {
            $users = app(\App\Services\UserManager::Class)->allWithProfiles();
            $this->relations['ref Users'] = $users;    
        }
        
        if (isset($this->fields[$field['name']]))
        {
            if (is_array($this->fields[$field['name']]))
            {
                foreach ($this->fields[$field['name']] as $k => $v)
                {
                    $this->fields[$field['name']][$k] =  app(\App\Services\UserManager::Class)->find($v);
                }
            }
            else
            {
                $this->fields[$field['name']] = app(\App\Services\UserManager::Class)->find($this->fields[$field['name']]);
            }
        }
        else
        {
            $this->fields[$field['name']] = null;
        }
    }

    private function getObjectRelation($field, Schema $schema)
    {
        $index = 'ref ' . $schema->id;
        if (! isset($this->relations[$index]))
        {
            $elements = app(\App\Services\ObjectManager::Class)->all($schema);
            $this->relations[$index] = $elements;    
        }
        
        if (isset($this->fields[$field['name']]))
        {
            if (is_array($this->fields[$field['name']]))
            {
                foreach ($this->fields[$field['name']] as $k => $v)
                {
                    $this->fields[$field['name']][$k] =  app(\App\Services\ObjectManager::Class)->find($schema, $v);
                }
            }
            else
            {
                $this->fields[$field['name']] = app(\App\Services\ObjectManager::Class)->find($schema, $this->fields[$field['name']]);
            }
        }
        else
        {
            $this->fields[$field['name']] = null;
        }
    }

    private function getRelation($field)
    {
        $code = str_replace('ref ', '', $field['type']);
        if ($code == 'Users')
        {
            $this->getUserRelation($field);
        }
        else
        {
            $schema = app(\App\Services\SchemaManager::Class)->find($code);
            $this->getObjectRelation($field, $schema);
        }
    }

    public function withRelations(): Object
    {
        $this->relations = [];

        foreach ($this->schema->fields as $field)
        {
            if (mb_strpos($field['type'], 'ref ') !== false)
            {
                $this->getRelation($field);
            }
        }
        return $this;
    }

    public function shortView(): String
    {
        if (isset($this->schema->viewData->shortView))
        {
            $template = $this->schema->viewData->shortView;
            foreach ($this->fields as $key => $field){
                if ((is_string($field) || is_numeric($field)) && mb_strpos($template, ":$key:") !== false)
                {
                    $template = str_replace(":$key:", $field, $template);
                }
            }
            $template = str_replace(":id:", $this->id, $template);
            return $template;
        }
        else
        {
            return $this->id;
        }
    }

}
