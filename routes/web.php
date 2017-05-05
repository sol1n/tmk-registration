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
  Route::get('/schemas/{schema}/edit', 'SchemasController@ShowSchemaEditForm');
  Route::post('/schemas/{id}/edit', 'SchemasController@EditSchema');
  Route::get('/schemas/{id}/delete', 'SchemasController@DeleteSchema');

  Route::get('/settings/', 'SettingsController@ShowSettingsForm');
  Route::post('/settings/', 'SettingsController@SaveSettings');

  Route::get('/users/', 'UsersController@ShowList');
  Route::get('/users/new/', 'UsersController@ShowCreateForm');
  Route::post('/users/new/', 'UsersController@CreateUser');
  Route::get('/users/{id}/', 'UsersController@ShowForm');
  Route::post('/users/{id}/', 'UsersController@SaveUser');
  Route::get('/users/{code}/delete', 'UsersController@DeleteUser');
  
  Route::get('/roles/', 'RolesController@ShowList');
  Route::get('/roles/new/', 'RolesController@ShowCreateForm');
  Route::post('/roles/new/', 'RolesController@CreateRole');
  Route::get('/roles/{code}/', 'RolesController@ShowForm');
  Route::post('/roles/{code}/', 'RolesController@SaveRole');
  Route::get('/roles/{code}/delete', 'RolesController@DeleteRole'); 

  Route::get('/{schema}/', 'ObjectsController@ShowCollection');
  Route::get('/{schema}/new/', 'ObjectsController@ShowCreateForm');
  Route::post('/{schema}/create/', 'ObjectsController@CreateObject');
  Route::get('/{schema}/{object}/', 'ObjectsController@ShowObject');
  Route::post('/{schema}/{object}/', 'ObjectsController@SaveObject');
  Route::get('/{schema}/{object}/delete', 'ObjectsController@DeleteObject');
});
  



