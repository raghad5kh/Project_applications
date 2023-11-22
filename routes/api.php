<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

//Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

//Route::post('/register', [AuthController::class, 'register']);
//Route::post('/login', [AuthController::class,'login']);
//Route::post('/logout', [AuthController::class,'logout'])->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
Route::post('/createGroup', [GroupController::class, 'store']);
// Route::get('/showGroupFiles/{id}', [FileController::class,'showGroupFiles']);
// Route::post('/addToGroup', [FileController::class,'addToGroup']);

Route::prefix('/file')->controller(FileController::class)
    ->group(function () {
        Route::post('/upload', 'upload');
        Route::post('/edit', 'edit');
        Route::post('/rename', 'rename');
        Route::post('/book', 'book');
        Route::post('/unBook', 'unBook');
        Route::get('/myFiles', 'myFiles');
    });
Route::prefix('/group/file')->controller(FileController::class)
    ->group(function () {
        Route::post('/add', 'addToGroup');
        
        Route::get('/{id}', 'showGroupFiles');
    });
Route::delete('/group/{group_id}/file/{file_id}', [FileController::class, 'removeFromGroup']);
