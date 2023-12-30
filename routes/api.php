<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupFileController;
use App\Http\Controllers\HistoryController;

//use App\Http\Controllers\TestController;
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
    Route::middleware(['LogRequests','transactional'])->group(function () {

        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});


Route::prefix('group')->group(function () {

    Route::middleware(['LogRequests','transactional'])->group(function () {

        Route::post('/store', [GroupController::class, 'store']);
        Route::post('/groupMember', [GroupController::class, 'groupMember']);
        Route::post('/destroy/{id}', [GroupController::class, 'destroy']);
        Route::get('/allGroups', [GroupController::class, 'allGroups']);
        Route::get('/usersGroup/{id}', [GroupController::class, 'usersGroup']);
        Route::get('/viewUserGroup/{name}', [GroupController::class, 'viewUserGroup']);
        Route::post('/deleteMember/{group_id}/{user_id}', [GroupController::class, 'deleteMember']);
    });

    Route::get('test', [FileController::class, 'index']);


});

Route::prefix('/file')->controller(FileController::class)
    ->group(function () {
        Route::middleware(['LogRequests','transactional'])->group(function () {
            Route::post('/upload', 'upload');
            Route::get('/read/{file_id}', 'read');
            Route::post('/edit', 'edit');
            Route::post('/rename', 'rename');
            Route::post('/book', 'book');
            Route::post('/unBook', 'unBook');
            Route::get('/myFiles', 'myFiles');
            //delete file
            Route::delete('/delete/{file_id}', 'delete');
        });
    });

Route::prefix('group')->controller(GroupFileController::class)
    ->group(function () {
        Route::middleware(['LogRequests','transactional'])->group(function () {

            Route::post('/add', 'addToGroup');
            Route::get('/{id}', 'showGroupFiles');
            Route::post('/file/add', 'addToGroup');
            Route::get('{group_id}/file/showAll', 'showGroupFiles');
            Route::get('/{group_id}/file/showToAdd', 'showGroupFilesToAdding');
            Route::get('/{group_id}/file/showUnBooked', 'showunBookedFiles');
            Route::delete('/{group_id}/file/{file_id}', 'removeFromGroup');

        });

    Route::prefix('group')->controller(GroupFileController::class)
        ->group(function () {
            Route::middleware(['LogRequests'])->group(function () {

                Route::post('/add', 'addToGroup');
                Route::get('/{id}', 'showGroupFiles');
                Route::post('/file/add', 'addToGroup');
                Route::get('{group_id}/file/showAll', 'showGroupFiles');
                Route::get('/{group_id}/file/showToAdd', 'showGroupFilesToAdding');
                Route::get('/{group_id}/file/showUnBooked', 'showunBookedFiles');
                Route::delete('/{group_id}/file/{file_id}', 'removeFromGroup');
            });
        });

    Route::prefix('/file')->controller(FileController::class)
        ->group(function () {
            Route::get('/myFiles', 'myFiles');
        });

Route::middleware(['transactional','LogRequests'])->group(
    function () {
        Route::prefix('group')->controller(GroupFileController::class)

            ->group(function () {
                Route::get('/read/{file_id}', 'read');
                Route::get('/showAll', 'showGroupFiles');
                Route::get('/showToAdd', 'showGroupFilesToAdding');
                Route::get('/showUnBooked', 'showunBookedFiles');
            });
        Route::prefix('/history')->controller(HistoryController::class)
            ->group(function () {
                Route::get('/file/{file_id}', 'fileHistory');
                Route::get('/user/{user_id}', 'userHistory');
            });
    });

    Route::middleware('transactional')->group(
        function () {
            Route::prefix('group')->controller(GroupFileController::class)
                ->group(function () {
                    Route::post('/add', 'addToGroup');
                    Route::post('/file/add', 'addToGroup');
                    Route::delete('/{group_id}/file/{file_id}', 'removeFromGroup');
                });
            Route::prefix('/file')->controller(FileController::class)
                ->group(function () {
                    Route::post('/upload', 'upload');
                    Route::post('/edit', 'edit');
                    Route::post('/rename', 'rename');
                    Route::post('/book', 'book');
                    Route::post('/unBook', 'unBook');
                    Route::delete('/delete/{file_id}', 'delete');
                });
        }
    );
