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
Route::post('/register/user', 'App\Http\Controllers\UserController@create')->withoutMiddleware(['App\Http\Middleware\VerifyBearerToken']);

// User
Route::get('/verify-email/{token}', 'App\Http\Controllers\UserController@emailVerify')->withoutMiddleware(['App\Http\Middleware\VerifyBearerToken']);

//Password
Route::post('/forgot-password', 'App\Http\Controllers\ForgotPasswordController@forgotPassword')->withoutMiddleware(['App\Http\Middleware\VerifyBearerToken']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->group(function () {
    Route::get('/your-protected-route', function(){
        return 'Can access now!!';
    });

    // User
    Route::get('/users', 'App\Http\Controllers\UserController@get');
    Route::get('/user/{id}', 'App\Http\Controllers\UserController@getId');
    Route::get('/users/card-totals', 'App\Http\Controllers\UserController@getCardData');
    Route::post('/user/update/{user_id}', 'App\Http\Controllers\UserController@update');
    Route::get('/teams', 'App\Http\Controllers\UserController@teams');
    Route::get('/team/{user_id}', 'App\Http\Controllers\UserController@team');
    Route::get('/member/{user_id}', 'App\Http\Controllers\UserController@member');
    Route::post('/childcount', 'App\Http\Controllers\UserController@apifindChildCount');
    Route::post('/generatereferralcode', 'App\Http\Controllers\UserController@ApigenerateReferralCode');
    //Transactions
    Route::get('/transactions/get', 'App\Http\Controllers\TransactionController@get');
    Route::get('/transactions/earnings', 'App\Http\Controllers\TransactionController@earnings');
    Route::post('/transaction/make-payment', 'App\Http\Controllers\TransactionController@makePayment');
    Route::post('/transaction/commission', 'App\Http\Controllers\TransactionController@APIcommissionDistribution2');

    // Products
    Route::post('/add/new-product', 'App\Http\Controllers\ProductController@create');
    Route::delete('/product/delete/{product_id}', 'App\Http\Controllers\ProductController@delete');
    Route::put('/product/update-status/{product_id}', 'App\Http\Controllers\ProductController@updateStatus');
    Route::put('/product/update-details/{product_id}', 'App\Http\Controllers\ProductController@update');
    Route::get('/products', 'App\Http\Controllers\ProductController@show');
    Route::get('/active-products', 'App\Http\Controllers\ProductController@activeProducts');
});
