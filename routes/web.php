<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', 'AuthController@ShowAuthForm');
Route::post('/login', 'AuthController@ProcessLogin');

Route::group(['middleware' => ['appercodeAuth']], function () {
  
  Route::get('/', 'SchemasController@ShowDashboard');

  Route::get('/schemas/', 'SchemasController@ShowSchemaList');
  Route::get('/schemas/new/', 'SchemasController@ShowSchemaCreateForm');
  Route::post('/schemas/new/', 'SchemasController@NewSchema');
  Route::get('/schemas/{code}/edit', 'SchemasController@ShowSchemaEditForm');
  Route::post('/schemas/{code}/edit', 'SchemasController@EditSchema');
  Route::get('/schemas/{code}/delete', 'SchemasController@DeleteSchema');

  Route::get('/settings/', 'SettingsController@ShowSettingsForm');
  Route::post('/settings/', 'SettingsController@SaveSettings');

  Route::get('/users/', 'UsersController@ShowList');
  Route::get('/roles/', 'RolesController@ShowList');

  Route::get('/{code}/', 'ObjectsController@ShowCollection');
  Route::get('/{code}/new/', 'ObjectsController@ShowCreateForm');
  Route::post('/{code}/create/', 'ObjectsController@CreateObject');
  Route::get('/{code}/{object}/', 'ObjectsController@ShowObject');
  Route::post('/{code}/{object}/', 'ObjectsController@SaveObject');
  Route::get('/{code}/{object}/delete', 'ObjectsController@DeleteObject');
});
  



