<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SchemaManager;
use App\Services\ObjectManager;

class ObjectsController extends Controller
{

    public function ShowCollection($schemaCode)
    {
        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        $objectManager = new ObjectManager();
        $objects = $objectManager->all($schema);

        return view('object/list', [
        'selected' => $schema->id,
        'schema' => $schema,
        'objects' => $objects
      ]);
    }

    public function ShowObject($schemaCode, $objectCode)
    {
        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        $objectManager = new ObjectManager();
        $object = $objectManager->find($schema, $objectCode);

        return view('object/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'object' => $object
      ]);
    }

    public function SaveObject(Request $request, $schemaCode, $objectCode)
    {
        $fields = $request->except('_token');

        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        $objectManager = new ObjectManager();
        $object = $objectManager->save($schema, $objectCode, $fields);
      
        return redirect('/' . $schema->id . '/' . $object->id);
    }

    public function ShowCreateForm($schemaCode)
    {
        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        return view('object/create', [
        'selected' => $schema->id,
        'schema' => $schema,
        ]);
    }

    public function CreateObject(Request $request, $schemaCode)
    {
        $fields = $request->except('_token');

        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        $objectManager = new ObjectManager();
        $object = $objectManager->create($schema, $fields);

        return redirect('/' . $schema->id . '/' . $object->id);
    }

    public function DeleteObject(Request $request, $schemaCode, $objectCode)
    {
        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        $objectManager = new ObjectManager();
        $objectManager->delete($schema, $objectCode);

        return redirect('/' . $schema->id . '/');
    }
}
