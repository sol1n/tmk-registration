<?php

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;
use App\Exceptions\Schema\SchemaSaveException;
use App\Exceptions\Schema\SchemaCreateException;
use App\Exceptions\Schema\SchemaDeleteException;
use App\Exceptions\Schema\SchemaNotFoundException;

class Schema
{
    public $id;
    public $title;
    public $fields;
    public $isDeferredDeletion;
    public $isLogged;

    private function prepareField(Array $field): Array
    {
        $field['localized'] = $field['localized'] == 'true';
        $field['title'] = (String) $field['title'];
        if (isset($field['deleted'])){
            unset($field['deleted']);
        }
        return $field;
    }

    private function getChanges(Array $data): Array
    {
        $changes = [];

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

    public static function create(Array $data, $token): Schema
    {
        $fields = [
            "id" => (String)$data['name'],
            "title" => (String)$data['title'],
            "isLogged" => $data['isLogged'],
            "isDeferredDeletion" => $data['isDeferredDeletion'],
            "fields" => []
        ];

        foreach ($data['fields'] as $field)
        {
            $fields['fields'][] = [
                "localized" => $field['localized'] == "true",
                "name" => (String)$field['name'],
                "type" => (String)$field['type'],
                "title" => (String)$field['title']
            ];
        }

        $client = new Client;
        try {
            $r = $client->post(env('APPERCODE_SERVER')  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $token
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

        return $schema;
    }

    public static function list(String $token): Collection
    {
        $client = new Client;
        $r = $client->get(env('APPERCODE_SERVER')  . 'schemas/?take=-1', ['headers' => [
            'X-Appercode-Session-Token' => $token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);
        $result = new Collection;

        foreach ($json as $raw) {
            $result->push(static::build($raw));
        }

        return $result;
    }

    public static function get(String $id, String $token): Schema
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER')  . 'schemas/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new SchemaNotFoundException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($json);
    }

    public function save(Array $data, String $token): Schema
    {
        $changes = $this->getChanges($data);

        $client = new Client;
        try {
            $r = $client->put(env('APPERCODE_SERVER')  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ], 'json' => $changes]);
        } catch (RequestException $e) {
            throw new SchemaSaveException;
        };

        return self::get($this->id, $token);
    }

    public function delete(String $token): Bool
    {
        $client = new Client;
        try {
            $r = $client->delete(env('APPERCODE_SERVER')  . 'schemas/' . $this->id, ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new SchemaDeleteException;
        };

        return true;
    }
}
