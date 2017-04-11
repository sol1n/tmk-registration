<?php

namespace App\Http\Controllers;

use App\User;
use App\Schema;
use App\Object;
use App\Services\SchemaManager;
use Illuminate\Http\Request;
use App\Exceptions\WrongCredentialsException;

class BasicController extends Controller
{
    public function ShowDashboard(Request $request)
    {
        return view('dashboard', [
        'selected' => 'dashboard'
      ]);
    }

    public function ShowCollection(Request $request, $code)
    {
        $manager = new SchemaManager($request->user->getToken());
        $schema = $manager->getBySlug($code);

        $objects = $manager->getObjects($schema);

        return view('schema/list', [
        'selected' => $schema->id,
        'schema' => $schema,
        'objects' => $objects
      ]);
    }

    public function ShowObject(Request $request, $schemaCode, $objectCode)
    {
        $manager = new SchemaManager($request->user->getToken());
        $schema = $manager->getBySlug($schemaCode);

        $object = $manager->getObject($schema, $objectCode);

        return view('schema/object', [
        'selected' => $schema->id,
        'schema' => $schema,
        'object' => $object
      ]);
    }

    public function SaveObject(Request $request, $schemaCode, $objectCode)
    {
        $fields = $request->except('_token');
        $manager = new SchemaManager($request->user->getToken());
        $schema = $manager->getBySlug($schemaCode);

        $object = Object::byRawData($objectCode, $fields, $schema);
        $object = $object->save($request->user->getToken());
      
        return redirect('/' . $schema->id . '/' . $object->id);
    }

    public function ShowAuthForm()
    {
        return view('auth/login', [
        'message' => session('login-error')
      ]);
    }

    public function ProcessLogin(Request $request)
    {
        try {
            User::login($request);
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Wrong сredentials data');
            return redirect('/login');
        }

        return redirect('/');
    }
}
