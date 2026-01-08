<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Auth routes for SPA (need session middleware)
Route::post('/api/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);
Route::post('/api/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::middleware('auth')->get('/api/user', function (Request $request) {
    return $request->user()->load('tenant');
});
Route::middleware('auth:sanctum')->post('/api/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
