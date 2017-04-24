<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\SettingsSaveException;
use App\Exceptions\SettingsGetException;

class Settings
{
    private $token;
    public $title;
    public $languages;
    public $userProfiles;
    public $newUsersRole;
    public $emailSettings;
    public $fcmServerKey;
    public $version;
    public $systemLogs;
    public $properties;
    public $id;
    public $createdAt;
    public $updatedAt;
    public $isDeleted;

    const CACHE_ID = 'settings';
    const CACHE_LIFETIME = 5;


    public function __construct()
    {
        $user = new User;
        $this->token = $user->token();

        if (! $data = self::getFromCache())
        {
            $data = self::fetch($this->token);
        }
        $this->build($data);
    }

    private static function fetch(String $token): Array
    {
        $client = new Client;
        try {
            $r = $client->get(env('APPERCODE_SERVER') . 'settings', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new SettingsGetException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);
        
        self::saveToCache($data);

        return $data;
    }

    private function build(Array $data): Settings
    {
        $this->title = $data['title'];
        $this->languages = $data['languages'];
        $this->userProfiles = $data['userProfiles'];
        $this->newUsersRole = $data['newUsersRole'];
        $this->emailSettings = $data['emailSettings'];
        $this->fcmServerKey = $data['fcmServerKey'];
        $this->version = $data['version'];
        $this->systemLogs = $data['systemLogs'];
        $this->properties = $data['properties'];
        $this->id = $data['id'];
        $this->createdAt = new Carbon($data['createdAt']);
        $this->updatedAt = new Carbon($data['updatedAt']);
        $this->isDeleted = $data['isDeleted'];

        return $this;
    }

    private static function saveToCache($data)
    {
        Cache::put(self::CACHE_ID, $data, self::CACHE_LIFETIME);
    }

    private static function getFromCache()
    {
        if (Cache::has(self::CACHE_ID)) {
            return Cache::get(self::CACHE_ID);
        } else {
            return null;
        }
    }

    public function save(Array $data): Settings
    {

        foreach ($data as $key => $value)
        {
            if ($this->{$key} != $value)
            {
                $this->{$key} = $value;    
            }
        }

        $json = [];
        foreach ($this as $key => $value)
        {
            if ($key != 'token')
            {
                $json[$key] = $value;
            }
        }

        $client = new Client;
        try {
            $r = $client->put(env('APPERCODE_SERVER') . 'settings', ['headers' => [
                'X-Appercode-Session-Token' => $this->token
            ], 'json' => $json]);
        } catch (RequestException $e) {
            throw new SettingsSaveException;
        };

        $data = self::fetch($this->token);

        $this->build($data);

        return $this;
    }
}
