<?php

namespace App\Http\Controllers;

use App\Schema;
use Illuminate\Http\Request;
use App\Services\SchemaManager;

class SchemasController extends Controller
{
    public function ShowDashboard()
    {
        return view('dashboard', [
        'selected' => 'dashboard'
      ]);
    }

    public function ShowSchemaList()
    {
        return view('schema/list', [
        'selected' => 'schema-list'
      ]);
    }

    public function ShowSchemaCreateForm(SchemaManager $manager)
    {
        return view('schema/create', [
        'selected' => 'schema-new',
        'fieldTypes' => $manager->fieldTypes()
      ]);
    }

    public function NewSchema(SchemaManager $manager, Request $request)
    {
        $data = $request->except(['_token', 'action']);
        $data['isLogged'] = $data['isLogged'] == 'true' ? true : false;
        $data['isDeferredDeletion'] = $data['isDeferredDeletion'] == 'true' ? true : false;

        return $manager->create($data)->jsonResponse();
    }

    public function ShowSchemaEditForm(SchemaManager $manager, Schema $schema)
    {
        return view('schema/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'fieldTypes' => $manager->fieldTypes()
      ]);
    }

    public function EditSchema(SchemaManager $manager, Request $request, String $id)
    {
        $action = $request->input('action');
        $data = $request->except(['_token', 'action']);
        $data['isLogged'] = $data['isLogged'] == 'true' ? true : false;
        $data['isDeferredDeletion'] = $data['isDeferredDeletion'] == 'true' ? true : false;

        return $manager->save($id, $data)->jsonResponse();
    }

    public function DeleteSchema(SchemaManager $manager, String $id)
    {
        return $manager->delete($id)->httpResponse('list');
    }
}
