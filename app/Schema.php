<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;
use App\Exceptions\Schema\SchemaSaveException;
use App\Exceptions\Schema\SchemaCreateException;
use App\Exceptions\Schema\SchemaDeleteException;
use App\Exceptions\Schema\SchemaListGetException;
use App\Exceptions\Schema\SchemaNotFoundException;
use App\Traits\Controllers\ModelActions;


class Schema
{
    use ModelActions;

    public $id;
    public $title;
    public $fields;
    public $isDeferredDeletion;
    public $isLogged;

    protected function baseUrl(): String
    {
        return 'schemas';
    }

    public function getSingleUrl(): String
    {
        return '/' . app(Backend::Class)->code . '/' . $this->baseUrl() . '/' . $this->id . '/edit/';
    }

    private function prepareField(Array $field): Array
    {
        $field['localized'] = $field['localized'] == 'true';
        $field['multiple'] = isset($field['multiple']) && $field['multiple'] == 'true';
        $field['title'] = (String) $field['title'];

        if (isset($field['deleted'])){
            unset($field['deleted']);
        }
        if ($field['multiple'])
        {
            $field['type'] = "[" . $field['type'] . "]";
        }
        return $field;
    }

    private function getChanges(Array $data): Array
    {
        $changes = [];

        if (isset($data['viewData']))
        {
            $viewData = $data['viewData'];
            unset($data['viewData']);

            $this->viewData = ($this->viewData) ? [] : $this->viewData;

            foreach ($viewData as $key => $field)
            {
                $this->viewData[$key] = $field;
            }
            
            $changes[] = [
                'action' => 'Change',
                'key' => $this->id . '.viewData',
                'value' => json_encode($this->viewData),
            ];
        }

        if (isset($data['deletedFields']))
        {
            $deletedFields = $data['deletedFields'];
            unset($data['deletedFields']);

            foreach ($deletedFields as $fieldName => $fieldData){
                $changes[] = [
                    'action' => 'Delete',
                    'key' => $this->id . '.' . $fieldName,
                ];
            }
        }

        if (isset($data['fields'])){
            $fields = $data['fields'];
            unset($data['fields']);
            foreach ($fields as $fieldName => &$fieldData){
                $field = [];
                $fieldData = $this->prepareField($fieldData);
                foreach ($this->fields as $key => $value){

                    if ($fieldName == $value['name']){
                        $field = $value;
                    }
                }

                foreach ($fieldData as $key => $value){
                    if ($field && $value != $field[$key])
                    {
                        if ($key == 'name')
                        {
                            $changes[] = [
                                'action' => 'Change',
                                'key' => $this->id . '.' . $fieldName ,
                                'value' => $value,
                            ];  
                        }                        
                        elseif ($key == 'multiple')
                        {
                            $newValue = $value ? '[' . $field['type'] . ']' : $field['type'];
                            $newFieldDate = $fieldData;
                            unset($newFieldDate['multiple']);
                            $newFieldDate['type'] = $newValue;
                            $changes[] = [
                                'action' => 'Delete',
                                'key' => $this->id . '.' . $fieldName ,
                            ];
                            $changes[] = [
                                'action' => 'New',
                                'key' => $this->id,
                                'value' => $newFieldDate
                            ];
                        }
                        elseif ($key == 'type')
                        {
                            $changes[] = [
                                'action' => 'Delete',
                                'key' => $this->id . '.' . $fieldName ,
                            ];
                            $changes[] = [
                                'action' => 'New',
                                'key' => $this->id,
                                'value' => $fieldData
                            ];
                        }
                        else{
                            $changes[] = [
                                'action' => 'Change',
                                'key' => $this->id . '.' . $fieldName . '.' . $key,
                                'value' => $value,
                            ];
                        }
                        
                    }
                }
            }
        }

        if (isset($data['newFields']))
        {
            $newFields = $data['newFields'];
            unset($data['newFields']);

            foreach ($newFields as $fieldName => $fieldData){
                $changes[] = [
                    'action' => 'New',
                    'key' => $this->id,
                    'value' => $this->prepareField($fieldData)
                ];
            }
        }
        
        foreach ($data as $name => $value){
            if ($value != $this->{$name}){
                $changes[] = [
                    'action' => 'Change',
                    'key' => $this->id . '.' . $name,
                    'value' => $value
                ];
            }
        }

        return $changes;
    }

    public static function create(Array $data, Backend $backend): Schema
    {
        $fields = [
            "id" => (String)$data['name'],
            "title" => (String)$data['title'],
            "isLogged" => $data['isLogged'],
            "isDeferredDeletion" => $data['isDeferredDeletion'],
            "viewData" => $data['viewData'],
            "fields" => []
        ];

        foreach ($data['fields'] as $field)
        {
            $type = (String)$field['type'];
            if ($field['multiple'] == 'true')
            {
                $type = "[$type]";
            }
            $fields['fields'][] = [
                "localized" => $field['localized'] == "true",
                "name" => (String)$field['name'],
                "type" => $type,
                "title" => (String)$field['title']
            ];
        }

        $client = new Client;
        try {
            $r = $client->post($backend->url  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $fields]);
        } catch (RequestException $e) {
            throw new SchemaCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        return self::build($json);
    }

    public static function build(Array $data): Schema
    {
        $schema = new static();
        $schema->id = $data['id'];
        $schema->title = $data['title'] ? $data['title'] : $data['id'];
        $schema->fields = $data['fields'];
        $schema->createdAt = new Carbon($data['createdAt']);
        $schema->updatedAt = new Carbon($data['updatedAt']);
        $schema->isDeferredDeletion = $data['isDeferredDeletion'];
        $schema->isLogged = $data['isLogged'];
        $schema->viewData = is_array($data['viewData']) ? $data['viewData'] : json_decode($data['viewData']);

        foreach ($schema->fields as &$field)
        {
            if (mb_strpos($field['type'], '[') !== false)
            {
                $field['multiple'] = true;
                $field['type'] = preg_replace('/\[(.+)\]/', '\1', $field['type']);
            }
            else
            {
                $field['multiple'] = false;
            }
        }

        return $schema;
    }

    public static function list(Backend $backend): Collection
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'schemas/?take=-1', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        }
        catch (RequestException $e) {
            throw new SchemaListGetException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        $result = new Collection;

        foreach ($json as $raw) {
            $result->push(static::build($raw));
        }

        return $result;
    }

    public static function get(String $id, Backend $backend): Schema
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'schemas/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new SchemaNotFoundException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($json);
    }

    public function save(Array $data, Backend $backend): Schema
    {
        $changes = $this->getChanges($data);

        $client = new Client;
        try {
            $r = $client->put($backend->url  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $changes]);
        } catch (RequestException $e) {
            throw new SchemaSaveException;
        };

        return self::get($this->id, $backend);
    }

    public function delete(Backend $backend): Schema
    {
        $client = new Client;
        try {
            $r = $client->delete($backend->url  . 'schemas/' . $this->id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new SchemaDeleteException;
        };

        return $this;
    }

    private function getUserRelation()
    {
        if (! isset($this->relations['ref Users']))
        {
            $users = app(\App\Services\UserManager::Class)->allWithProfiles();
            $this->relations['ref Users'] = $users;    
        }
    }

    private function getObjectRelation(Schema $schema)
    {
        $index = 'ref ' . $schema->id;
        if (! isset($this->relations[$index]))
        {
            $elements = app(\App\Services\ObjectManager::Class)->all($schema);
            $this->relations[$index] = $elements;    
        }
    }

    private function getRelation($field)
    {
        $code = str_replace('ref ', '', $field['type']);
        if ($code == 'Users')
        {
            $this->getUserRelation();
        }
        else
        {
            $schema = app(\App\Services\SchemaManager::Class)->find($code);
            $this->getObjectRelation($schema);
        }
    }

    public function withRelations()
    {
        $this->relations = [];

        foreach ($this->fields as $key => $field)
        {
            if (mb_strpos($field['type'], 'ref ') !== false)
            {
                $this->getRelation($field);
            }
        }

        return $this;
    }
}
