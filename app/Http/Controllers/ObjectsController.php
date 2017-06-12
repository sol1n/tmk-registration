<?php

namespace App\Http\Controllers;

use App\Helpers\AjaxResponse;
use App\Object;
use App\Schema;
use App\Backend;
use App\Services\SchemaManager;
use App\Services\UserManager;
use Illuminate\Http\Request;
use App\Services\ObjectManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class ObjectsController extends Controller
{
    public function ShowCollection(Backend $backend, Schema $schema, ObjectManager $manager)
    {
        return view('object/list', [
        'selected' => $schema->id,
        'schema' => $schema->withRelations(),
        'objects' => $manager->all($schema)
      ]);
    }

    public function ShowObject(Backend $backend, Schema $schema, ObjectManager $manager, $id)
    {
        return view('object/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'object' => $manager->find($schema, $id)->withRelations(),
        'backend' => $backend
      ]);
    }

    public function SaveObject(Backend $backend, Request $request, Schema $schema, ObjectManager $manager, $id)
    {
        $fields = $request->except(['_token', 'action']);
        return $manager->save($schema, $id, $fields)->httpResponse();
    }

    public function ShowCreateForm(Backend $backend, Schema $schema)
    {
        return view('object/create', [
        'selected' => $schema->id,
        'schema' => $schema->withRelations(),
        ]);
    }

    public function CreateObject(Request $request, Backend $backend, Schema $schema, ObjectManager $manager)
    {
        $fields = $request->except(['_token', 'action']);
        return $manager->create($schema, $fields)->httpResponse();
    }

    public function DeleteObject(Backend $backend, Schema $schema, ObjectManager $manager, $id)
    {
        return $manager->delete($schema, $id)->httpResponse('list');
    }

}
