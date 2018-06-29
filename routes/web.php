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


Route::get('/lecture', 'SiteController@LectureForm');
Route::get('/form/{company}/', 'SiteController@ShowEditForm')->name('company');
Route::get('/form', 'SiteController@ShowEditForm')->name('form');
Route::get('/form/{company}/user/{profile}/remove', 'SiteController@RemoveMember')->name('removeMember');
Route::post('/form/{company}/user/new/', 'SiteController@NewMember')->name('newMember');
Route::post('/form/{company}/user/{profile}/', 'SiteController@ProcessMember')->name('saveMember');

Route::get('/', 'AuthController@ShowAuthForm')->name('index');
Route::post('/login', 'AuthController@ProcessLogin');
Route::get('/logout', 'AuthController@ProcessLogout')->name('logout');