<?php

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;
use App\Exceptions\Schema\SchemaNotFoundException;

class Schema
{
    public $id;
    public $title;
    public $fields;

    public static function build(Array $data): Schema
    {
        $schema = new static();
        $schema->id = $data['id'];
        $schema->title = $data['title'] ? $data['title'] : $data['id'];
        $schema->fields = $data['fields'];
        $schema->createdAt = new Carbon($data['createdAt']);
        $schema->updatedAt = new Carbon($data['updatedAt']);

        return $schema;
    }

    public static function list(String $token): Collection
    {
        $client = new Client;
        $r = $client->get(env('APPERCODE_SERVER')  . 'schemas', ['headers' => [
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
        $changes = [];

        $fields = $data['fields'];
        unset($data['fields']);

        foreach ($data as $name => $value){
            if ($value != $this->{$name}){
                $changes[] = [
                    'action' => 'Change',
                    'key' => $this->id . '.' . $name,
                    'value' => $value
                ];
            }
        }

        foreach ($fields as $fieldName => $fieldData){
            $field = [];
            foreach ($this->fields as $key => $value){

                if ($fieldName == $value['name']){
                    $field = $value;
                }
            }

            if ($fieldData['localized'] == 'false'){
                $fieldData['localized'] = false;
            }
            else{
                $fieldData['localized'] = true;   
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
                    else{
                        $changes[] = [
                            'action' => 'Change',
                            'key' => $this->id . '.' . $fieldName . '.' . $key,
                            'value' => $value,
                            'old' => $field
                        ];
                    }
                    
                }
            }
        }

        $client = new Client;
        try {
            $r = $client->put(env('APPERCODE_SERVER')  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ], 'json' => $changes]);
        } catch (RequestException $e) {
            throw new SchemaNotFoundException;
        };

        return self::get($this->id, $token);
    }
}
