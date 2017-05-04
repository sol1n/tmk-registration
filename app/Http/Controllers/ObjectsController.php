<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SchemaManager;
use App\Services\ObjectManager;

class ObjectsController extends Controller
{

    public function ShowCollection($schemaCode)
    {
        $schema = app(SchemaManager::class)->find($schemaCode);
        $objects = app(ObjectManager::class)->all($schema);

        return view('object/list', [
        'selected' => $schema->id,
        'schema' => $schema,
        'objects' => $objects
      ]);
    }

    public function ShowObject($schemaCode, $objectCode)
    {
        $schema = app(SchemaManager::class)->find($schemaCode);
        $object = app(ObjectManager::class)->find($schema, $objectCode);

        return view('object/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'object' => $object
      ]);
    }

    public function SaveObject(Request $request, $schemaCode, $objectCode)
    {
        $fields = $request->except(['_token', 'action']);

        $schema = app(SchemaManager::class)->find($schemaCode);
        $object = app(ObjectManager::class)->save($schema, $objectCode, $fields);
      
        if ($request->input('action') == 'save')
        {
            return redirect('/' . $schema->id . '/');    
        }
        else
        {
            return redirect('/' . $schema->id . '/' . $object->id);
        }
        
    }

    public function ShowCreateForm($schemaCode)
    {
        $schema = app(SchemaManager::class)->find($schemaCode);

        return view('object/create', [
        'selected' => $schema->id,
        'schema' => $schema,
        ]);
    }

    public function CreateObject(Request $request, $schemaCode)
    {
        $fields = $request->except(['_token', 'action']);

        $schema = app(SchemaManager::class)->find($schemaCode);
        $object = app(ObjectManager::class)->create($schema, $fields);

        if ($request->input('action') == 'save')
        {
            return redirect('/' . $schema->id . '/');    
        }
        else
        {
            return redirect('/' . $schema->id . '/' . $object->id);
        }
    }

    public function DeleteObject(Request $request, $schemaCode, $objectCode)
    {
        $schema = app(SchemaManager::class)->find($schemaCode);
        app(ObjectManager::class)->delete($schema, $objectCode);

        return redirect('/' . $schema->id . '/');
    }
}
