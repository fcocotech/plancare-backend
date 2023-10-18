<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', 'App\Http\Controllers\AuthController@login')->withoutMiddleware(['App\Http\Middleware\VerifyBearerToken']);
// Security Question  
Route::get('/security-questions/get-all', 'App\Http\Controllers\SecurityQuestionController@get')->withoutMiddleware(['App\Http\Middleware\VerifyBearerToken']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->group(function () {
    Route::get('/your-protected-route', function(){
        return 'Can access now!!';
    });

    // User
    Route::post('/user/update/{user_id}', 'App\Http\Controllers\UserController@update');
});
