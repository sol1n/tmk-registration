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
  Route::get('/', 'CollectionsController@ShowDashboard');
  Route::get('/{code}/', 'CollectionsController@ShowCollection');
  Route::get('/{code}/{object}/', 'CollectionsController@ShowObject');
  Route::post('/{code}/{object}/', 'CollectionsController@SaveObject');
});
  



