<?php

namespace App;

use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;

class Object
{
    public $fields;
    public $schema;

    private static function prepareRawData($data, $schema){
      foreach ($schema->fields as $field){
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

    public function save($token){
      $url = env('APPERCODE_SERVER');

      $client = new Client;
      try {
          $r = $client->put($url . 'objects/' . $this->schema->id . '/' . $this->id, ['headers' => [
              'X-Appercode-Session-Token' => $token
          ], 'json' => $this->fields]);
      }
      catch (ServerException $e){
          throw new ObjectSaveException;
      }

      return $this;
    }

    public static function build($data, Schema $schema){
        $object = new static();
        $object->id = $data['id'];
        $object->fields = $data;

        $object->schema = $schema;

        return $object;
    }

    public static function byRawData($id, $data, Schema $schema){


        $object = new static();
        $object->id = $id;
        $object->fields = self::prepareRawData($data, $schema);

        $object->schema = $schema;

        return $object;
    }
}
