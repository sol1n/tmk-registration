<?php

namespace App\Http\Controllers;

use App\File;
use App\Backend;
use App\Helpers\AjaxResponse;
use App\Object;
use App\Services\FileManager;
use App\Services\RoleManager;
use App\Services\UserManager;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class FilesController extends Controller
{

    public function ShowFolder(Backend $backend, String $id = '')
    {
//        app(FileManager::class)->deleteFile('8606198e-04b9-46a3-8f66-ec7a36d4bbaf');
        $route = $id ? explode('/',$id) : [];
        $folderResult = app(FileManager::class)->getFolder($route);
        $folder = $folderResult['folder'];
        $children = $folderResult['children'];
        $breadcrumbs = $folderResult['breadcrumbs'];
        $sortField = \session('sortField');
        $sortOrder = \session('sortOrder');
        $backend = app(Backend::class);
        return view('files/folder', [
            'folder' => $folder,
            'children' => $children,
            'breadcrumbs' => $breadcrumbs,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'backend' => $backend
        ]);
    }

    public function GetFile(Backend $backend, $id) {
        $result = app(FileManager::class)->getFile($id);
        if ($result['file']->length > 0) {
            if ($result['fileResult']['statusCode'] == '404') {
                return view('files/file-not-found');
            } else {
                $filePath = $result['fileResult']['fileName'];
                $file = $result['file'];
                return response()->download($filePath, $file->name);
            }
        }
        else {
            return view('files/no-file');
        }
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
        $backend = app(Backend::class);
        return view('files/search', [
            'files' => $files,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'backend' => $backend
        ]);
    }

    public function UploadFile(Request $request)
    {
        $response = new AjaxResponse();
        $file = $request->file('fileUpload');
        $parentId = $request->input('parentId') ?? File::ROOT_PARENT_ID;
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

    public function Edit(Backend $backend, $id)
    {
        $roles = app(RoleManager::class)->all()->pluck('id');
        $users = app(UserManager::class)->getMappedUsers();
        $file = app(FileManager::class)->one($id);
        $folders = app(FileManager::class)->getFolders();
        $breadcrumbs = app(FileManager::class)->getPath($id);
        $backLink = $breadcrumbs[count($breadcrumbs) - 2]->link;
        $permissions = $file->getRightsMap();
        //$backend = app(Backend::class);

        return view('files/edit', [
            'file' => $file,
            'folders' => $folders,
            'breadcrumbs' => $breadcrumbs,
            'backLink' => $backLink,
            'roles' => $roles,
            'users' => $users,
            'permissions' => $permissions,
            'backend' => $backend
        ]);
    }

    public function Save(Request $request, Backend $backend, $id) {

        $this->validate($request, [
            'name' => 'required',
            'parentId' => 'required',
            'permissions' => 'rights_unique'
        ]);


        $fields = $request->except(['_token', 'backLink', 'action', 'permissions']);
        $file = $request->file('file');
        $permissions = $request->input('permissions');
        $adds = [];
        foreach ($permissions as $permission) {
            foreach ($permission['rights'] as $right) {
                $adds[$right . '.' . $permission['id']] = true;
            }
        }
        $fields['rights'] = $adds ? $adds : null;

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
