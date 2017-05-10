<?php

namespace App;


use App\Services\UserManager;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection;
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

        foreach ($data['myFiles'] as $item) {
            $result->push(static::build($item, $users, static::BASE_LINK));
        }

        return $result;
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
        $file->name = $data['file']['name'];
        $file->ownerId = (integer)$data['file']['ownerId'];
        $file->ownerName = isset($users[$file->ownerId]) ? $users[$file->ownerId] : '';
        $file->parentId = (integer)$data['file']['parentId'];
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
        $file->children = new Collection();

        if (isset($data['children'])){
            $childBaseLink = $baseLink . $file->id . '/';
            foreach ($data['children'] as $child){
                $file->children->push(static::build($child, $users, $childBaseLink));
            }
        }
        return $file;
    }

    public function getSize(){
        return $this->length;
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

}