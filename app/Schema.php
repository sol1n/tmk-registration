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

    public static function build($data): Schema
    {
        $schema = new static();
        $schema->id = $data['id'];
        $schema->title = $data['title'] ? $data['title'] : $data['id'];
        $schema->fields = $data['fields'];
        $schema->createdAt = new Carbon($data['createdAt']);
        $schema->updatedAt = new Carbon($data['updatedAt']);

        return $schema;
    }

    public static function list($token): Collection
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

    public static function get($id, $token): Schema
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
}
