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

        $data = $request->except('_token');
        $schema = $manager->save($schemaCode, $data);

        return response()->json(['status' => 'saved']);        
    }
}
