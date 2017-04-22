<?php

namespace App\Http\Controllers;

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

    public function ShowSchemaCreateForm()
    {
        return view('schema/create', [
        'selected' => 'schema-new',
      ]);
    }

    public function ShowSchemaEditForm($schemaCode)
    {
        $manager = new SchemaManager;
        $schema = $manager->find($schemaCode);

        return view('schema/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'fieldTypes' => SchemaManager::fieldTypes()
      ]);
    }

    public function EditSchema(Request $request, $schemaCode)
    {
        $manager = new SchemaManager;

        $action = $request->input('action');

        $data = $request->except(['_token', 'action']);
        $data['isLogged'] = $data['isLogged'] == 'true' ? true : false;
        $data['isDeferredDeletion'] = $data['isDeferredDeletion'] == 'true' ? true : false;
        $schema = $manager->save($schemaCode, $data);

        if ($action == 'save')
        {
            return response()->json(['status' => 'saved', 'action' => 'redirect', 'url' => '/schemas/']);
        }
        else
        {
            return response()->json(['status' => 'saved', 'action' => 'reload']);
        }
    }
}
