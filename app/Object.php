<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use App\Exceptions\Object\ObjectSaveException;
use App\Exceptions\Object\ObjectNotFoundException;
use App\Traits\Controllers\ModelActions;

class Object
{
    use ModelActions;

    public $fields;
    public $schema;

    protected function baseUrl(): String
    {
        return $this->schema->id;
    }

    private static function prepareRawData(array $data, Schema $schema): array
    {
        $systemFields = ['id', 'createdAt', 'updatedAt', 'ownerId'];
        foreach ($systemFields as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        foreach ($schema->fields as $field) {
            switch ($field['type']) {
              case 'String':
                  $data[$field['name']] = (String)$data[$field['name']];
                  break;
              case 'Text':
                  $data[$field['name']] = (String)$data[$field['name']];
                  break;
              case 'Integer':
                  $data[$field['name']] = (Integer)$data[$field['name']];
                  break;
              case 'Double':
                  $data[$field['name']] = (Float)$data[$field['name']];
                  break;
              
              default:

                  break;
          }
        }

        foreach ($data as $key => $value)
        {
            if (is_null($value))
            {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function save(array $fields, $token): Object
    {
        $this->fields = static::prepareRawData($fields, $this->schema);

        $client = new Client;
        try {
            $r = $client->put(env('APPERCODE_SERVER') . 'objects/' . $this->schema->id . '/' . $this->id, ['headers' => [
              'X-Appercode-Session-Token' => $token
          ], 'json' => $this->fields]);
        } catch (ServerException $e) {
            throw new ObjectSaveException;
        }

        return $this;
    }

    public static function get(Schema $schema, $id, $token): Object
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER') . 'objects/' . $schema->id . '/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (ClientException $e) {
            throw new ObjectNotFoundException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($schema, $json);
    }

    public static function create(Schema $schema, $fields, $token): Object
    {
        $fields = self::prepareRawData($fields, $schema);

        $client = new Client;
        $r = $client->post(env('APPERCODE_SERVER') . 'objects/' . $schema->id, ['headers' => [
            'X-Appercode-Session-Token' => $token
        ], 'json' => $fields]);

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($schema, $json);
    }

    public function delete($token): Object
    {
        $client = new Client;
        $r = $client->delete(env('APPERCODE_SERVER') . 'objects/' . $this->schema->id . '/' . $this->id, ['headers' => [
            'X-Appercode-Session-Token' => $token
        ]]);

        return $this;
    }

    public static function list(Schema $schema, $token): Collection
    {
        $list = new Collection;

        $url = env('APPERCODE_SERVER');
        $client = new Client;
        $r = $client->get($url . 'objects/' . $schema->id, ['headers' => [
            'X-Appercode-Session-Token' => $token
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
            $this->fields[$field['name']] = app(\App\Services\UserManager::Class)->find($this->fields[$field['name']]);
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
            $this->fields[$field['name']] = app(\App\Services\ObjectManager::Class)->find($schema, $this->fields[$field['name']]);
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
            return $id;
        }
    }

}
