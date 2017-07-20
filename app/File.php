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

    public static function getbaseLink()
    {
        return '/' . app(Backend::class)->code . static::BASE_LINK;
    }

    /**
     * Retursn files tree
     * @param String $token
     * @return Collection
     */
    public static function tree(Backend $backend) : Collection
    {
        $client = new Client();
        try {
            $r = $client->get($backend->url . '/files/tree', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new RoleGetListException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        $result = new Collection();

//        $users = app(UserManager::class)->getMappedUsers();
        $users = static::getTreeUsers($data['myFiles']);
        $users = User::list($backend, ['where' => json_encode(['id' => ['$in' => $users]])]);
        $users = $users->mapWithKeys(function ($item) {
            return [$item->id => $item->username];
        });
        $result = self::constructFlatTree($data['myFiles'], $users, static::getbaseLink());
        return $result;
    }

    private static function getTreeUsers($tree) {
        $result = [];

        $walk = function ($data) use (&$walk){
            $result = [];
            if (is_array($data) and $data) {
                foreach ($data as $item) {
                    $result[] = $item['file']['ownerId'];
                    if ($item['children']) {
                        $result = array_merge($result, $walk($item['children']));
                    }
                }
            }
            return $result;
        };

        $result = $walk($tree);

        return array_unique($result);
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
        $rootFile->link = static::getbaseLink();
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

    private static function getUser($id, $backend) {
        $user = User::get($id, $backend);
        return [$user->id => $user->username];
    }

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
            $file->link = static::getbaseLink() . 'get/' . $file->id;
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

    /**
     * Returns array with keys
     * userId - user id (null if it is a role for role)
     * right - current right
     * role - role (null if it is a role for user)
     * @param String $right
     * @return array
     */
    private function parseRight(String $right) {
        $result = ['userId' => null, 'right' => '', 'role' => null];
        if ($right) {
            $chunks = explode('.', $right);
            if ($chunks[1] == 'user') { //user role
                $result['userId'] = $chunks[2];
            }
            else{
                $result['role'] = $chunks[1];
            }
            $result['right'] = $chunks[0];
        }
        return $result;
    }

    public function getRights($key = 'total')
    {
        $result = '';
        $tmp = [];
        foreach ($this->rights[$key] as $rightName => $right){
            if ($right){
                $tmp[] = $this->parseRight($rightName)['right'];
            }
        }
        $result = join('|', $tmp);
        return $result;
    }

    /**
     * @param string $type 'adds'|'total'
     * @return array
     */
    public function getRightsMap($type = 'adds'){
        $result = [];
        if (isset($this->rights[$type]) and $this->rights[$type]) {
            foreach ($this->rights[$type] as $rightName => $right) {
                if ($right) {
                    $parsedRight = $this->parseRight($rightName);
                    if ($parsedRight['userId']) {
                        if (!isset($result[$parsedRight['userId']])) {
                            $result[$parsedRight['userId']] = ['id' => 'user.' . $parsedRight['userId'], 'rights' => []];
                        }
                        $result[$parsedRight['userId']]['rights'][] = $parsedRight['right'];
                    } else {
                        if (!isset($result[$parsedRight['role']])) {
                            $result[$parsedRight['role']] = ['id' => $parsedRight['role'], 'rights' => []];
                        }
                        $result[$parsedRight['role']]['rights'][] = $parsedRight['right'];
                    }
                }
            }
        }
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


    public static function addFolder($fields, Backend $backend) : File
    {
        $client = new Client;
        $fields['fileType'] = 'directory';
        $r = $client->post($backend->url . 'files', ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ], 'json' => $fields]);

        $json = ['file' => json_decode($r->getBody()->getContents(), 1)];

        $users = static::getUser($json['file']['ownerId'], $backend);

        $folder = self::build($json, $users, $fields['path']);

        return $folder;// static::build($schema, $json);
    }

    public static function createFile($props, Backend $backend){
        $client = new Client;
        $fields['fileType'] = 'directory';

        $r = $client->post($backend->url . 'files', ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ], 'json' => $props]);

        $createdFile = null;
        $response  = $r->getBody()->getContents();
        if ($response) {

            $json = ['file' => json_decode($response, 1)];

            $users = static::getUser($json['file']['ownerId'],$backend);

            $createdFile = self::build($json, $users, '');
        }

        return $createdFile;
    }

    public static function uploadFile($fileId, $multipart, Backend $backend)
    {
        $result = true;
        $client = new Client;
        $fields['fileType'] = 'directory';

        try {
            $res = $client->request('POST', $backend->url . 'files/' . $fileId . '/upload', [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'multipart' => $multipart,
            ], ['debug' => true]);
        }
        catch (ServerException $e) {
            $result = false;
        }

        return $result;
    }

    public static function delete($fileId, $props, Backend $backend) {
        $client = new Client;
        $props['_method'] = 'DELETE';
        $r = $client->delete($backend->url . 'files/' . $fileId . '?markupAsDeleted=true', ['headers' => [
            'X-Appercode-Session-Token' => $backend->token
        ]]);

        //$response  = $r->getBody()->getContents();

        return true;
    }

    public static function update($fileId, $props, Backend $backend){
        $client = new Client();
        try {
            $r = $client->put($backend->url . 'files/' . $fileId, [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'json' => $props
            ]);
        } catch (ServerException $e) {
            //dd($e);
            throw new FileUpdateException();
        }

        $json = ['file' => json_decode($r->getBody()->getContents(), 1)];

        $users = static::getUser($json['file']['ownerId'], $backend);

        $updatedFile = static::build($json, $users, '');

        return $updatedFile;
    }

    public function getFile(Backend $backend) {
        $result = ['result' => true, 'fileName' => '', 'statusCode' => ''];
        $tmpFile = tempnam(sys_get_temp_dir(), '');
        $client = new Client();
        $resource = fopen($tmpFile, 'w');
        try {
            $r = $client->request('GET', $backend->url . 'files/' . $this->id . '/download',
                [
                    'headers' => ['X-Appercode-Session-Token' => $backend->token],
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