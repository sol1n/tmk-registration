<?php

namespace App;

use App\Backend;
use App\Services\ObjectManager;
use App\Services\UserManager;
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
use Monolog\Handler\SyslogHandler;

class Object
{
    use ModelActions, FieldsFormats;

    public $fields;
    public $schema;

    protected function baseUrl(): String
    {
        return $this->schema->id;
    }

    public function save($data, Backend $backend, $language = null): Object
    {
        $this->fields = static::prepareRawData($data, $this->schema);

        $headers = [
            'X-Appercode-Session-Token' => $backend->token
        ];

        if ($language !== null)
        {
            $headers['X-Appercode-Language'] = $language;
        }

        $client = new Client;
        try {
            $r = $client->put($backend->url . 'objects/' . $this->schema->id . '/' . $this->id, [
                'headers' => $headers, 
                'json' => $this->fields
            ]);
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

    public static function list(Schema $schema, Backend $backend, $query = null): Collection
    {
        $list = new Collection;

        if ($query) {
            $query = http_build_query($query);
        }
        else {
            $query = http_build_query(['take' => 200]);
        }

        $client = new Client;
        $url = $backend->url . 'objects/' . $schema->id . ($query ? '?' . $query : '');
        $r = $client->get($url, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);

        foreach ($json as $rawData) {
            $list->push(Object::build($schema, $rawData));
        }

        return $list;
    }

    public static function listWithLangs(Schema $schema, Backend $backend, $query = null, $language): Collection
    {
        $list = new Collection;

        if ($query) {
            $query = http_build_query($query);
        }
        else {
            $query = http_build_query(['take' => 200]);
        }

        $headers = [
            'X-Appercode-Session-Token' => $backend->token
        ];

        $client = new Client;
        $url = $backend->url . 'objects/' . $schema->id . ($query ? '?' . $query : '');
        $r = $client->get($url, ['headers' => $headers]);

        $json = json_decode($r->getBody()->getContents(), 1);

        $tempData = [];

        foreach ($json as $rawData) {
            $tempData[$rawData['id']] = $rawData;
        }

        $headers['X-Appercode-Language'] = $language;

        $r = $client->get($url, ['headers' => $headers]);

        $json = json_decode($r->getBody()->getContents(), 1);

        foreach ($json as $localizedRawData) {
            $id = $localizedRawData['id'];
            $localizedData = [
                $language => $localizedRawData
            ];
            $list->push(Object::build($schema, $tempData[$id], $localizedData));
        }

        return $list;
    }

    public static function count(Schema $schema, Backend $backend) {
        $result = 0;
        $client = new Client;
        $url = $backend->url . 'objects/' . $schema->id . '?take=1&count=true';
        $r = $client->get($url, ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);
        if ($r->getHeader('x-appercode-totalitems')){
            $result = $r->getHeader('x-appercode-totalitems')[0];
        }

        return $result;
    }

    public static function build(Schema $schema, $data, $localizedData = null): Object
    {
        $object = new static();
        $object->id = $data['id'];
        $object->createdAt = new Carbon($data['createdAt']);
        $object->updatedAt = new Carbon($data['updatedAt']);
        $object->fields = self::prepareRawData($data, $schema);

        if (! is_null($localizedData))
        {
           $object->languages = [];
            foreach ($localizedData as $language => $data)
            {
                $object->languages[$language] = self::prepareRawData($data, $schema);
            } 
        }
        
        $object->schema = $schema;

        return $object;
    }

    private function getUserRelation($field)
    {
        if (! isset($this->relations['ref Users']))
        {
            $count = $this->getRelationUserCount($field);
            $users = [];
            if ($count > config('objects.ref_count_for_select')) {
                $userIds = [];
                if (isset($this->fields[$field['name']])) {
                    if (is_array($this->fields[$field['name']])) {
                        $userIds = $this->fields[$field['name']];
                    } else {
                        $userIds = [$this->fields[$field['name']]];
                    }
                }
                $users = $userIds ? app(\App\Services\UserManager::Class)->findMultipleWithProfiles($userIds) : [];
            }
            else {
                $users = app(\App\Services\UserManager::Class)->allWithProfiles();
            }
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
            elseif (is_object($this->fields[$field['name']]))
            {
                $this->fields[$field['name']] = $this->fields[$field['name']];
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
            $count = $this->getRelationObjectCount($field, $schema);
            if ($count > config('objects.ref_count_for_select')) {
                $objectIds = [];
                if (is_array($this->fields[$field['name']])) {
                    $objectIds = $this->fields[$field['name']];
                } else {
                    $objectIds = [$this->fields[$field['name']]];
                }
                $elements = $objectIds ? app(\App\Services\ObjectManager::Class)->search($schema, ['where' => json_encode(['id' => ['$in' => $objectIds]])]) : [];
            }
            else {
                $elements = app(\App\Services\ObjectManager::Class)->all($schema);
            }
            $this->relations[$index] = $elements;    
        }
        
        if (isset($this->fields[$field['name']]))
        {
            if (is_array($this->fields[$field['name']]))
            {
                foreach ($this->fields[$field['name']] as $k => $v)
                {
                    if (is_object($v))
                    {
                        $this->fields[$field['name']][$k] = $v;
                    }
                    else
                    {
                        $this->fields[$field['name']][$k] =  app(\App\Services\ObjectManager::Class)->find($schema, $v);
                    }
                }
            }
            elseif (is_object($this->fields[$field['name']]))
            {
                $this->fields[$field['name']] = $this->fields[$field['name']];
            }
            else
            {
                if ($this->fields[$field['name']] != "00000000-0000-0000-0000-000000000000")
                {
                    $this->fields[$field['name']] = app(\App\Services\ObjectManager::Class)->find($schema, $this->fields[$field['name']]);
                }
            }
        }
        else
        {
            $this->fields[$field['name']] = null;
        }
    }

    private function getFileRelation($field)
    {
        $this->relations['ref Files'] = [];
    }

    private function getRelation($field)
    {
        $code = str_replace('ref ', '', $field['type']);
        if ($code == 'Users')
        {
            $this->getUserRelation($field);
        }
        elseif ($code == 'Files')
        {
            $this->getFileRelation($field);
        }
        else
        {
            $schema = app(\App\Services\SchemaManager::Class)->find($code);
            $this->getObjectRelation($field, $schema);
        }
    }

    private function getRelationUserCount($field) {
        return app(UserManager::class)->count();
    }

    private function getRelationObjectCount($field, Schema $schema) {
        return app(ObjectManager::class)->count($schema);
    }

    public function getRelationCount($field) {
        if (mb_strpos($field['type'], 'ref') !== false) {
            $code = str_replace('ref ', '', $field['type']);
            if ($code == 'Users') {
                return $this->getRelationUserCount($field);
            } else {
                $schema = app(\App\Services\SchemaManager::Class)->find($code);
                return $this->getRelationObjectCount($field, $schema);
            }
        }
        return 0;
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

    public static function getShortViewFields(Schema $schema) {
        $viewFields = [];
        if (isset($schema->viewData->shortView))
        {
            $template = $schema->viewData->shortView;

            foreach ($schema->fields as $field) {
                if ((is_string($field['name']) || is_numeric($field['name'])) && mb_strpos($template, ":".$field['name'].":") !== false)
                {
                    $viewFields[] = $field['name'];
                }
            }
        }
        else
        {
            $viewFields = ['id'];
        }
        return $viewFields;
    }

}
