<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupFileController;
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


Route::prefix('group')->group(function () {
    Route::post('/store', [GroupController::class, 'store']);
    Route::post('/groupMember/{name_group}/{user}', [GroupController::class, 'groupMember']);
    Route::delete('/destroy/{id}', [GroupController::class, 'destroy']);
    Route::get('/allGroups', [GroupController::class, 'allGroups']);
    Route::get('/usersGroup/{id}', [GroupController::class, 'usersGroup']);
    Route::get('/viewUserGroup/{name}', [GroupController::class, 'viewUserGroup']);
});

Route::prefix('/file')->controller(FileController::class)
    ->group(function () {
        Route::post('/upload', 'upload');
        Route::post('/edit', 'edit');
        Route::post('/rename', 'rename');
        Route::post('/book', 'book');
        Route::post('/unBook', 'unBook');
        Route::get('/myFiles', 'myFiles');
        //delete file
    });

Route::prefix('group')->controller(GroupFileController::class)
    ->group(function () {
        Route::post('/file/add', 'addToGroup');    
        Route::get('{group_id}/file/showAll', 'showGroupFiles');
        Route::get('/{group_id}/file/showToAdd', 'showGroupFilesToAdding');    
        Route::get('/{group_id}/file/showUnBooked', 'showunBookedFiles');    
        Route::delete('/{group_id}/file/{file_id}', 'removeFromGroup');    
    });

