<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SchemaManager;
use App\Services\ObjectManager;

class CollectionsController extends Controller
{
    public function ShowDashboard()
    {
        return view('dashboard', [
        'selected' => 'dashboard'
      ]);
    }

    public function ShowCollection($schemaCode)
    {
        $schemaManager = new SchemaManager();
        $schema = $schemaManager->find($schemaCode);

        $objectManager = new ObjectManager();
        $objects = $objectManager->all($schema);

        return view('schema/list', [
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

        return view('schema/object', [
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
}
