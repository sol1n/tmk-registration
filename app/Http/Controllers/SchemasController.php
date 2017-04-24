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
        'fieldTypes' => SchemaManager::fieldTypes()
      ]);
    }

    public function NewSchema(Request $request)
    {
        $action = $request->input('action');
        $data = $request->except(['_token', 'action']);
        $data['isLogged'] = $data['isLogged'] == 'true' ? true : false;
        $data['isDeferredDeletion'] = $data['isDeferredDeletion'] == 'true' ? true : false;

        $schema = app(SchemaManager::class)->create($data);

        if ($action == 'save')
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/schemas/']);
        }
        else
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/schemas/' . $schema->id . '/edit/']);
        }
    }

    public function ShowSchemaEditForm($schemaCode)
    {
        $schema = app(SchemaManager::class)->find($schemaCode);

        return view('schema/form', [
        'selected' => $schema->id,
        'schema' => $schema,
        'fieldTypes' => SchemaManager::fieldTypes()
      ]);
    }

    public function EditSchema(Request $request, $schemaCode)
    {
        $action = $request->input('action');
        $data = $request->except(['_token', 'action']);
        $data['isLogged'] = $data['isLogged'] == 'true' ? true : false;
        $data['isDeferredDeletion'] = $data['isDeferredDeletion'] == 'true' ? true : false;
        $schema = app(SchemaManager::class)->save($schemaCode, $data);

        if ($action == 'save')
        {
            return response()->json(['status' => 'success', 'action' => 'redirect', 'url' => '/schemas/']);
        }
        else
        {
            return response()->json(['status' => 'success', 'action' => 'reload']);
        }
    }

    public function DeleteSchema($schemaCode)
    {
        app(SchemaManager::class)->delete($schemaCode);
        return redirect('/schemas/');
    }
}
