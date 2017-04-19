<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use App\Exceptions\ObjectSaveException;

class Object
{
    public $fields;
    public $schema;
    public $urls;

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
        $r = $client->get(env('APPERCODE_SERVER') . 'objects/' . $schema->id . '/' . $id, ['headers' => [
            'X-Appercode-Session-Token' => $token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($schema, $json);
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
}