<?php

namespace App\Http\Controllers;

use App\Schema;
use Illuminate\Http\Request;
use App\Services\ObjectManager;

class ObjectsController extends Controller
{
    public function ShowCollection(Schema $schema, ObjectManager $manager)
    {
        return view('object/list', [
        'selected' => $schema->id,
        'schema' => $schema,
        'objects' => $manager->all($schema)
      ]);
    }

    public function ShowObject(Schema $schema, ObjectManager $manager, $id)
    {
        return view('object/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'object' => $manager->find($schema, $id)->withRelations()
      ]);
    }

    public function SaveObject(Request $request, Schema $schema, ObjectManager $manager, $id)
    {
        $fields = $request->except(['_token', 'action']);
        return $manager->save($schema, $id, $fields)->httpResponse();
    }

    public function ShowCreateForm(Schema $schema)
    {
        return view('object/create', [
        'selected' => $schema->id,
        'schema' => $schema->withRelations(),
        ]);
    }

    public function CreateObject(Request $request, Schema $schema, ObjectManager $manager)
    {
        $fields = $request->except(['_token', 'action']);
        return $manager->create($schema, $fields)->httpResponse();
    }

    public function DeleteObject(Schema $schema, ObjectManager $manager, $id)
    {
        return $manager->delete($schema, $id)->httpResponse('list');
    }
}
