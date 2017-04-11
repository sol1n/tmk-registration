<?php

namespace App\Services;

use App\Schema;
use App\Object;
use Illuminate\Support\Collection;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SchemaNotFoundException;
use App\Exceptions\ObjectSaveException;

class SchemaManager
{
    private $list;
    private $token;

    const CACHE_ID = 'schemas';
    const CACHE_LIFETIME = 20;

    public function __construct($token)
    {
        $this->token = $token;

        if (! $rawData = $this->getFromCache()) {
            $rawData = $this->fetchRaw();
            $this->saveToCache($rawData);
        }

        $this->list = new Collection;
        foreach ($rawData as $rawData) {
            $this->list->push(Schema::build($rawData));
        }
    }

    private function getFromCache()
    {
        if (Cache::has(self::CACHE_ID)) {
            return Cache::get(self::CACHE_ID);
        } else {
            return null;
        }
    }

    private function saveToCache($data)
    {
        Cache::put(self::CACHE_ID, $data, self::CACHE_LIFETIME);
    }

    private function fetchRaw()
    {
        $url = env('APPERCODE_SERVER');
        $client = new Client;
        $r = $client->get($url . 'schemas', ['headers' => [
            'X-Appercode-Session-Token' => $this->token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);

        return $json;
    }

    public function getBySlug($id): Schema
    {
        foreach ($this->list as $schema) {
            if ($schema->id == $id) {
                return $schema;
            }
        }

        throw new SchemaNotFoundException;
    }

    public function getAll()
    {
        return $this->list;
    }

    public function getObjects(Schema $schema)
    {
        $list = new Collection;

        $url = env('APPERCODE_SERVER');
        $client = new Client;
        $r = $client->get($url . 'objects/' . $schema->id, ['headers' => [
            'X-Appercode-Session-Token' => $this->token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);

        foreach ($json as $rawData) {
            $list->push(Object::build($rawData, $schema));
        }

        return $list;
    }

    public function getObject(Schema $schema, $code)
    {
        $url = env('APPERCODE_SERVER');
        $client = new Client;
        $r = $client->get($url . 'objects/' . $schema->id . '/' . $code, ['headers' => [
            'X-Appercode-Session-Token' => $this->token
        ]]);

        $json = json_decode($r->getBody()->getContents(), 1);

        return Object::build($json, $schema);
    }
}
