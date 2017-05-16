<?php

namespace App\Http\Controllers;

use App\File;
use App\Helpers\AjaxResponse;
use App\Object;
use App\Services\FileManager;
use App\Services\UserManager;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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


    public function ShowFolder(String $id = '')
    {
//        app(FileManager::class)->deleteFile('8606198e-04b9-46a3-8f66-ec7a36d4bbaf');
        $route = $id ? explode('/',$id) : [];
        $folderResult = app(FileManager::class)->getFolder($route);
        $folder = $folderResult['folder'];
        $children = $folderResult['children'];
        $breadcrumbs = $folderResult['breadcrumbs'];
        $sortField = \session('sortField');
        $sortOrder = \session('sortOrder');
        return view('/files/folder', [
            'folder' => $folder,
            'children' => $children,
            'breadcrumbs' => $breadcrumbs,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder
        ]);
    }

    public function GetFile($id) {
        $result = app(FileManager::class)->getFile($id);
        $filePath = $result['filepath'];
        $file = $result['file'];
        return response()->download($filePath, $file->name);
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

    public function addFolder(Request $request)
    {
        $response = new AjaxResponse();
        $folderName = $request->get('name');
        $parentId = $request->get('parentId');
        $path = $request->get('path');
        $folder = app(FileManager::class)->addFolder([
            'name' => $folderName,
            'parentId' => $parentId,
            'path' => $path
        ]);
        if ($folder) {
            $response->data = $folder;
        }
        else{
            $response->setResponseError('File hasn`t been added');
        }
        return response()->json($response);
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

    public function UploadFile(Request $request)
    {
        $response = new AjaxResponse();
        $file = $request->file('fileUpload');
        $parentId = $request->input('parentId');
        if ($file->isValid()) {
            $fileProperties = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ];
            $result = app(FileManager::class)->createFile($fileProperties, $parentId);
            if (!$result['error']){
                $uploadResult = app(FileManager::class)->uploadFile($result['file']->id, $file);
                if ($uploadResult){
                    $response->data = $result['file'];
                }
                else{
                    app(FileManager::class)->deleteFile($result['file']->id);
                    $response->setResponseError('File hasn\'t been uploaded');
                }
            }
            else{
                $response->setResponseError($result['error']);
            }
        }
        else{
            $response->setResponseError('File is not valid');
        }
        return response()->json($response);
    }

    public function Delete(Request $request)
    {
        $response = new AjaxResponse();
        $fileId = $request->input('fileId');
        $result = app(FileManager::class)->deleteFile($fileId);
        if (!$result) {
            $response->setResponseError('File has not been deleted. Wrong request!');
        }
        return response()->json($response);
    }

    public function Restore(Request $request)
    {
        $response = new AjaxResponse();
        $fileId = $request->input('fileId');
        $result = app(FileManager::class)->restoreFile($fileId);
        if (!$result) {
            $response->setResponseError('File has not been Restored. Wrong request!');
        }
        return response()->json($response);
    }

    public function Edit($id)
    {
        $file = app(FileManager::class)->one($id);
        $folders = app(FileManager::class)->getFolders();
        $breadcrumbs = app(FileManager::class)->getPath($id);
        $backLink = $breadcrumbs[count($breadcrumbs) - 2]->link;
        return view('/files/edit', [
            'file' => $file,
            'folders' => $folders,
            'breadcrumbs' => $breadcrumbs,
            'backLink' => $backLink
        ]);
    }

    public function Save(Request $request, $id) {
        $fields = $request->except(['_token', 'action']);
        $file = $request->file('file');

        app(FileManager::class)->update($id, $fields, $file);

        if ($request->input('action') == 'save') {
            $backLink = $request->input('backLink');
            return redirect($backLink);
        }
        else {
            return back();
        }
    }
}
