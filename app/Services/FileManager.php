<?php

namespace App\Services;


use App\File;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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
        //$files = null;
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
     * 1. breadcrumbs - also array, each item contains link and name for constructing breadcrumbs
     * 2. folder - needed folder
     * 3. baselink - base link for this folder
     * @param array $route
     * @return array
     */
    public function getFolder(Array $route) : Array
    {
        $result = ['breadcrumbs' => [], 'folder' => [], 'baselink' => ''];
        $fileTree = $this->fetchCollection();
        $currentFolder = [];
        $links = File::BASE_LINK;
        $compile = function() use(&$result, &$links, &$fileTree, &$currentFolder) {
            $links .= $currentFolder->id . '/';
            $result['breadcrumbs'][] = ['link' => $links, 'name' => $currentFolder->name ?? $currentFolder->id];
            $fileTree = $currentFolder->children;
        };
        foreach ($route as $r){
            if ($currentFolder){
                $compile();
            }
            $currentFolder = $fileTree->first(function($value, $key) use($r){
                return $value->id == $r;
            });
            if (!$currentFolder){
                Throw new RouteNotFoundException('Route ' . join('/',$route) . ' doesn\'t exist');
            }
        }
        $compile();
        $result['baselink'] = $links;
        $result['folder'] = $currentFolder;
        return $result;
    }

    private function saveCollectionToCache($data)
    {
        $cacheId = self::CACHE_ID;
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
            if ($file->children and !$file->children->isEmpty()){
                $children = $file->children;
                $childrenResult = $this->doSearch($children, $query);
                foreach ($childrenResult as $item){
                    $result->push($item);
                }
            }
        }
        return $result;
    }
}