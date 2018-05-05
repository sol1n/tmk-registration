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

Route::get('/', 'SiteController@ShowAuthForm');
Route::get('/form/{company}/', 'SiteController@ShowEditForm');
Route::get('/form', 'SiteController@ShowEditForm');
Route::get('/form/{company}/user/{profile}/remove', 'SiteController@RemoveMember');
Route::post('/form/{company}/user/new/', 'SiteController@NewMember');
Route::post('/form/{company}/user/{profile}/', 'SiteController@ProcessMember');
Route::post('/form/{company}/', 'SiteController@ProcessForm');
Route::post('/login', 'SiteController@ProcessLogin');
Route::get('/logout', 'SiteController@ProcessLogout');

Route::get('/{backend}/login', 'AuthController@ShowAuthForm');
Route::post('/{backend}/login', 'AuthController@ProcessLogin');