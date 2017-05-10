<?php

namespace App\Http\Controllers;

use App\File;
use App\Services\FileManager;
use App\Services\UserManager;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class FilesController extends Controller
{
    public function ShowTree()
    {
        $u = new User();
        //dd(File::tree($u->token()));
        $fileTree = app(FileManager::class)->all();
        $sortField = \session('sortField');
        $sortOrder = \session('sortOrder');
        return view('/files/list', [
            'fileTree' => $fileTree,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder
        ]);
    }

    public function ShowFolder(String $id)
    {
        $route = explode('/',$id);
        $folderResult = app(FileManager::class)->getFolder($route);
        $folder = $folderResult['folder'];
        if ($folder){
            $folder->children = $folder->children->sortBy('fileType');
        }
        $breadcrumbs = $folderResult['breadcrumbs'];
        $sortField = \session('sortField');
        $sortOrder = \session('sortOrder');
        return view('/files/folder', [
            'folder' => $folder,
            'breadcrumbs' => $breadcrumbs,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder
        ]);
    }

    public function SetOrder(Request $request)
    {
        if ($request->has('field')){
            $field = $request->get('field');
            $order = $request->get('order') ?? 'desc';
            \session(['sortField' => $field]);
            \session(['sortOrder' => $order]);
        }
        return response()->json(['response' => 'ok']);
    }

    public function Search(Request $request)
    {
        $query = $request->get('query');
        $files = app(FileManager::class)->search($query);
        $sortField = \session('sortField');
        $sortOrder = \session('sortOrder');
        return view('/files/search', [
            'files' => $files,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder
        ]);
    }
}
