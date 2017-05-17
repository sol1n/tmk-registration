<?php

namespace App;


use App\Exceptions\Files\FileUpdateException;
use App\Services\UserManager;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
use Mockery\Exception;
use phpDocumentor\Reflection\Types\Integer;

class File
{

    public $id;
    public $name;
    public $ownerId;
    public $ownerName;
    public $parentId;
    public $fileType;
    public $shareStatus;
    public $createdAt;
    public $updatedAt;
    public $isDeleted;
    public $rights;
    public $length;
    public $children;
    public $link;

    CONST ROOT_PARENT_ID = '00000000-0000-0000-0000-000000000000';
    CONST BASE_LINK = '/files/';
    CONST ROOT_INDEX = 'root_element';

    /**
     * Retursn files tree
     * @param String $token
     * @return Collection
     */
    public static function tree(String $token) : Collection
    {
        $client = new Client();
        try {
            $r = $client->get(env('APPERCODE_SERVER') . 'files/tree', ['headers' => [
                'X-Appercode-Session-Token' => $token
            ]]);
        } catch (RequestException $e) {
            throw new RoleGetListException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        $result = new Collection();

        $users = app(UserManager::class)->getMappedUsers();

//        foreach ($data['myFiles'] as $item) {
//            $result->push(static::build($item, $users, static::BASE_LINK));
//        }
        $result = self::constructFlatTree($data['myFiles'], $users, static::BASE_LINK);
        return $result;
    }

    private static function constructFlatTree($data, $users, $baseLink){
        $result = [];

        $flatten = function ($data, $users, $baseLink) use (&$flatten){
            $result = [];
            if (is_array($data) and $data) {
                foreach ($data as $item) {
                    $file = static::build($item, $users, $baseLink);
                    $result[$file->id] = $file;
                    $childBaseLink = $baseLink . $file->id . '/';
                    if ($item['children']) {
                        $result = array_merge($result, $flatten($item['children'], $users, $childBaseLink));
                    }
                }
            }
            return $result;
        };

        $result = $flatten($data, $users, $baseLink);
        $rootFile = new File();
        $rootFile->link = '/files/';
        $rootFile->name = 'Main folder';
        $rootFile->fileType = 'directory';
        $result[static::ROOT_PARENT_ID] = $rootFile;

        foreach ($result as $item) {
            if ($item->parentId) {
                if ($item->parentId == static::ROOT_PARENT_ID){
                    $result[static::ROOT_PARENT_ID]->children[] = $item->id;
                }
                else {
                    $result[$item->parentId]->children[] = $item->id;
                }
            }
        }

        return collect($result);
    }

//    public static function folder(String $id, String $token)
//    {
//        $client = new Client();
//        try {
//            $r = $client->get(env('APPERCODE_SERVER') . 'files/' . $id, ['headers' => [
//                'X-Appercode-Session-Token' => $token
//            ]]);
//        } catch (RequestException $e) {
//            throw new RoleGetListException;
//        };
//
//        $data = json_decode($r->getBody()->getContents(), 1);
//
//        return $data;
//    }


    /**
     * Creates instance of this class from array
     * @param array $data
     * @param array $users
     * @param String $baseLink
     * @return File
     */
    public static function build($data, $users, $baseLink) : File
    {
        $file = new File();
        $file->id = $data['file']['id'];
        $file->name = $data['file']['name'] ?? $data['file']['id'];
        $file->ownerId = (integer)$data['file']['ownerId'];
        $file->ownerName = isset($users[$file->ownerId]) ? $users[$file->ownerId] : '';
        $file->parentId = $data['file']['parentId'];
        $file->fileType = $data['file']['fileType'];
        $file->shareStatus = $data['file']['shareStatus'];
        $file->createdAt = new Carbon($data['file']['createdAt']);
        $file->updatedAt = new Carbon($data['file']['updatedAt']);
        $file->isDeleted = (bool) $data['file']['isDeleted'];
        $file->rights = $data['file']['rights'];
        $file->length = (integer) $data['file']['length'];
        if ($file->fileType == 'directory'){
            $file->link = $baseLink . $file->id;
        }
        else{
            $file->link = static::BASE_LINK . 'get/' . $file->id;
        }
        $file->children = [];

//        if (isset($data['children'])){
//            $childBaseLink = $baseLink . $file->id . '/';
//            foreach ($data['children'] as $child){
//                $file->children->push(static::build($child, $users, $childBaseLink));
//            }
//        }
        return $file;
    }

    public function getSize(){
        if ($this->length < 1024 * 1024) {
            return $this->length . ' Bytes';
        }
        else{
            return $this->length / (1024 * 1024) .' MB';
        }
    }

    public function getRights()
    {
        $result = '';
        $tmp = [];
        foreach ($this->rights['total'] as $rightName => $right){
            if ($right){
                $tmp[] = explode('.', $rightName)[0];
            }
        }
        $result = join('|', $tmp);
        return $result;
    }

//    public static function get($id, $token): Object
//    {
//        $client = new Client;
//        $r = $client->get(env('APPERCODE_SERVER') . 'objects/' . $schema->id . '/' . $id, ['headers' => [
//            'X-Appercode-Session-Token' => $token
//        ]]);
//
//        $json = json_decode($r->getBody()->getContents(), 1);
//
//        return static::build($schema, $json);
//    }


    public static function addFolder($token, $fields) : File
    {
        $client = new Client;
        $fields['fileType'] = 'directory';
        $r = $client->post(env('APPERCODE_SERVER') . 'files', ['headers' => [
            'X-Appercode-Session-Token' => $token
        ], 'json' => $fields]);

        $json = ['file' => json_decode($r->getBody()->getContents(), 1)];

        $users = app(UserManager::class)->getMappedUsers();

        $folder = self::build($json, $users, $fields['path'].'/');

        return $folder;// static::build($schema, $json);
    }

    public static function createFile($props, $token){
        $client = new Client;
        $fields['fileType'] = 'directory';
        $r = $client->post(env('APPERCODE_SERVER') . 'files', ['headers' => [
            'X-Appercode-Session-Token' => $token
        ], 'json' => $props]);

        $createdFile = null;
        $response  = $r->getBody()->getContents();
        if ($response) {

            $json = ['file' => json_decode($response, 1)];

            $users = app(UserManager::class)->getMappedUsers();

            $createdFile = self::build($json, $users, '');
        }

        return $createdFile;
    }

    public static function uploadFile($fileId, $multipart, $token)
    {
        $client = new Client;
        $fields['fileType'] = 'directory';

        $res = $client->request('POST', env('APPERCODE_SERVER') . 'files/' . $fileId . '/upload', [
            'headers' => ['X-Appercode-Session-Token' => $token],
            'multipart' => $multipart,
        ], ['debug' => true]);

        return true;
    }

    public static function delete($fileId, $props, $token) {
        $client = new Client;
        $props['_method'] = 'DELETE';
        $r = $client->delete(env('APPERCODE_SERVER') . 'files/' . $fileId . '?markupAsDeleted=true', ['headers' => [
            'X-Appercode-Session-Token' => $token
        ]]);

        //$response  = $r->getBody()->getContents();

        return true;
    }

    public static function update($fileId, $props, $token){
        $client = new Client();
        try {
            $r = $client->put(env('APPERCODE_SERVER') . 'files/' . $fileId, [
                'headers' => ['X-Appercode-Session-Token' => $token],
                'json' => $props
            ]);
        } catch (ServerException $e) {
            throw new FileUpdateException();
        }

        $users = app(UserManager::class)->getMappedUsers();

        $json = ['file' => json_decode($r->getBody()->getContents(), 1)];

        $updatedFile = static::build($json, $users, '');

        return $updatedFile;
    }

    public function getFile($token) {
        $result = ['result' => true, 'fileName' => '', 'statusCode' => ''];
        $tmpFile = tempnam(sys_get_temp_dir(), '');
        $client = new Client();
        $resource = fopen($tmpFile, 'w');
        try {
            $r = $client->request('GET', env('APPERCODE_SERVER') . 'files/' . $this->id . '/download',
                [
                    'headers' => ['X-Appercode-Session-Token' => $token],
                    'sink' => $tmpFile
                ]);
            $result['statusCode'] = $r->getStatusCode();
            $result['fileName'] = $tmpFile;
        }
        catch (ClientException $exception) {
            $result['result'] = false;
            $result['statusCode'] = $exception->getResponse()->getStatusCode();
        }
        //dd($r->getBody()->getContents());
        return $result;
    }
}