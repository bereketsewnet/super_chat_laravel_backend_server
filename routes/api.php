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
    Route::any('/send_notice','LoginController@send_notice')->middleware('is_login');
    Route::any('/bind_fcmtoken','LoginController@bind_fcmtoken')->middleware('is_login');
    Route::any('/upload_photo','LoginController@upload_photo')->middleware('is_login');
    Route::any('/update_profile','LoginController@update_profile')->middleware('is_login');
    Route::any('/get_rtc_token','AccessTokenController@get_rtc_token')->middleware('is_login');

});