<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// if you need you dirctly set like this
// Route::get('/test', 'App\Http\Controllers\TestController@index');

// if you need creat group with fasad use like this 
// first you will crete new folder inside Controllers
Route::group(['namespace'=>'App\Http\Controllers\Api'], function() {
    Route::any('/login', 'LoginController@login');
    Route::any('/get_profile', 'LoginController@get_profile');
    Route::any('/contact', 'LoginController@contact')->middleware('is_login');

});