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

Route::get('/login', 'BasicController@ShowAuthForm');
Route::post('/login', 'BasicController@ProcessLogin');

Route::group(['middleware' => ['appercode']], function(){
  Route::get('/', 'BasicController@ShowDashboard');
  Route::get('/{code}/', 'BasicController@ShowCollection');
  Route::get('/{code}/{object}/', 'BasicController@ShowObject');
  Route::post('/{code}/{object}/', 'BasicController@SaveObject');
});


