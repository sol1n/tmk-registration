<?php

namespace App\Services;


use App\File;
use App\Helpers\Breadcrumb;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class FileManager
{
    CONST CACHE_ID = 'files';
    const CACHE_LIFETIME = 60;

    public function __construct()
    {
        $user = new User();
        $this->token = $user->token();
    }

    public function fetchCollection(){
        $files = $this->getCollectionFromCache();
//        $files = null;
        if (is_null($files)) {
            $files = File::tree($this->token);
            $this->saveCollectionToCache($files);
        }
        return $files;
    }

    /**
     * Returns list of files
     * @return Collection|null
     */
    public function all()
    {
        $files = $this->fetchCollection();
        return $files;
    }

    /**
     * Returns folder by route
     * Result array has three keys:
     * 1. breadcrumbs - array, each item contains link and name for constructing breadcrumbs
     * 2. folder - needed folder
     * 3. children - folder children collection
     * 4. baselink - base link for this folder
     * @param array $route
     * @return array
     */
    public function getFolder(Array $route) : Array
    {
        $result = ['breadcrumbs' => [], 'folder' => [], 'baselink' => ''];
        $fileTree = $this->fetchCollection();
        $currentFolder = [];
        $links = File::BASE_LINK;
        $root = $fileTree->get(File::ROOT_PARENT_ID);
        $result['breadcrumbs'][] = new Breadcrumb('/files/', $root->name);
        $compile = function() use(&$result, &$links, &$currentFolder) {
            $links .= $currentFolder->id . '/';
            $result['breadcrumbs'][] = new Breadcrumb($links, $currentFolder->name ?? $currentFolder->id);
        };
        $currentFolder = $fileTree->get(File::ROOT_PARENT_ID);
        foreach ($route as $r){
            if (in_array($r, $currentFolder->children)){
                $currentFolder = $fileTree->get($r);
                $compile();
            }
            else{
                Throw new RouteNotFoundException('Route ' . join('/',$route) . ' doesn\'t exist');
            }
        }
        $children = new Collection();
        foreach ($currentFolder->children as $child) {
            $children->push($fileTree->get($child));
        }
        $result['baselink'] = $links;
        $result['folder'] = $currentFolder;
        $result['children'] = $children;
        return $result;
    }

    /**
     * Returns one File
     * @param $id
     * @return mixed|null
     */
    public function one($id)
    {
        $result = null;
        $files = $this->all();
        $result = $files->get($id);
        return $result;
    }

    public function saveCollectionToCache($data)
    {
        $cacheId = self::CACHE_ID;
        Cache::forget($cacheId);
        Cache::put($cacheId, $data, self::CACHE_LIFETIME);
    }

    private function getCollectionFromCache()
    {
        $cacheId = self::CACHE_ID;

        if (Cache::has($cacheId)) {
            return Cache::get($cacheId);
        } else {
            return null;
        }
    }

    public function search($query)
    {
        $result = new Collection();
        $files = $this->all();
        $result = $this->doSearch($files, $query);
        return $result;
    }

    private function extractBaseFields($file){
        $newFile = $file;
        $newFile->children = [];
        return $newFile;
    }

    /**
     * @param $data
     * @param $query
     * @return Collection
     */
    private function doSearch($data, $query) {
        $result = new Collection();
        //$files = $this->all();
        foreach ($data as $file) {
            /**
             * @var File $file
             */
            if (str_contains($file->name, $query) or str_contains($file->id, $query)){
                $result->push($this->extractBaseFields($file));
            }
        }
        return $result;
    }

    public function putToTree($tree, File $item){
        $tree->put($item->id, $item);
        $element = $tree->get($item->parentId);
        $element->children[] = $item->id;
        $tree->put($item->parentId, $element);
        return $tree;
    }

    /**
     * Add folder
     * @param $fields
     * @return Object
     */
    public function addFolder($fields){
        if (!$fields['parentId']){
            $fields['parentId'] = File::ROOT_PARENT_ID;
        }
        $folder = File::addFolder($this->token, $fields);

        $files = $this->all();

        $files = $this->putToTree($files, $folder);

        $this->saveCollectionToCache($files);

        return $folder;
    }

    public function createFile($fileProperties, $parentId){
        $result = ['file' => null, 'error' => ''];
        $props = [
            'parentId' => $parentId,
            'name' => $fileProperties['name'],
            'isFile' => true,
            'fileType' =>  'file',
            'length' => $fileProperties['size']
        ];
        $file = File::createFile($props, $this->token);
        if ($file){
            $result['file'] = $file;

            $files = $this->all();
            $files = $this->putToTree($files, $file);
            $this->saveCollectionToCache($files);
        }
        else{
            $result['error'] = 'File wasn\'t created';
        }
        return $result;
    }

    public function uploadFile(String $fileId, UploadedFile $file){

        $multipart = [
                [
                    'name'     => 'file',
                    'filename' => $file->getClientOriginalName(),
                    'contents' => file_get_contents($file->getPathname()),
                ]
        ];

        $response = File::uploadFile($fileId, $multipart, $this->token);
        return $response;
    }

    private function updateItem($tree, $item) {
        $tree->put($item->id, $item);
        return $tree;
    }

    public function deleteFile($fileId)
    {
        $props = [
            'markupAsDeleted' => true
        ];
        $response = File::delete($fileId, $props, $this->token);
        if ($response) {
            $files = $this->all();
            $file = $files->get($fileId);
            $file->isDeleted = true;
            $files = $this->updateItem($files, $file);
            $this->saveCollectionToCache($files);
            return true;
        }
        else{
            return false;
        }
    }

    public function restoreFile($fileId)
    {
        $props = [
            'isDeleted' => false
        ];
        $response = File::update($fileId, $props, $this->token);
        if ($response) {
            $files = $this->all();
            $file = $files->get($fileId);
            $file->isDeleted = false;
            $files = $this->updateItem($files, $file);
            $this->saveCollectionToCache($files);
            return true;
        }
        else{
            return false;
        }
    }

    public function getFolders($isMapped = false)
    {
        $result = new Collection();
        $files = $this->all();
        $result = $files->where('fileType', 'directory');
        if ($isMapped) {
            $result = $result->mapWithKeys(function ($item)  {
                return [$item->id => $item->name];
            });
        }
        return $result;
    }

    public function getPath($id) {
        $result = [];
        $files = $this->all();
        $currentFolder = $files->get($id);
        while ($currentFolder) {
            $breadcrumb = new Breadcrumb($currentFolder->link, $currentFolder->name);
            array_unshift($result, $breadcrumb);
            $currentFolder = $files->get($currentFolder->parentId);
        }
        return $result;
    }

    public function update($fileId, $fields, UploadedFile $uploadFile = null) {
        $files = $this->all();
        $file = $files->get($fileId);
        $isSuccess = true;
        if ($uploadFile) {
            $fields['length'] = $uploadFile->getSize();
        }
        $result = File::update($fileId, $fields, $this->token);
        if ($result) {
            if ($uploadFile) {
                $isSuccess = $this->uploadFile($fileId, $uploadFile);
            }
        }
        else{
            $isSuccess = false;
        }
        if ($isSuccess){
            if ($result->parentId == $file->parentId) {
                $result->link = $file->link;
            }
            else {
                $oldParent = $files->get($file->parentId);
                $newParent = $files->get($result->parentId);
                if(($key = array_search($result->id, $oldParent->children)) !== false) {
                    unset($oldParent->children[$key]);
                }
                $newParent->children[] = $result->id;
                if ($result->fileType == 'directory') {
                    $result->link = $newParent->link . '/' . $result->id;
                }
                $files->put($oldParent->id, $oldParent);
                $files->put($newParent->id, $newParent);
            }
            $files->put($result->id, $result);
            $this->saveCollectionToCache($files);

            return true;
        }
        return false;
    }


    public function getFile($id)
    {
        $file = app(FileManager::class)->one($id);
        return ['fileResult' => $file->getFile($this->token), 'file' => $file];
    }
}