<?php

use App\Http\Controllers\Api\V1\Admin\ContentController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AnswerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Teacher\ReviewController;
use App\Http\Controllers\Api\V1\TextbookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('groups', [AuthController::class, 'groups']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
        });
    });

    Route::middleware('guest.auth')->group(function () {
        Route::get('textbook', [TextbookController::class, 'index']);
        Route::get('sections/{section}', [TextbookController::class, 'section'])->whereNumber('section');
        Route::get('lessons/{lesson}', [TextbookController::class, 'lesson'])->whereNumber('lesson');
        Route::get('glossary', [TextbookController::class, 'glossary']);
        Route::get('search', [TextbookController::class, 'search']);
    });

    Route::middleware(['auth:sanctum', 'role:any'])->group(function () {
        Route::get('answers', [AnswerController::class, 'index']);
        Route::post('tasks/{task}/answer', [AnswerController::class, 'store'])->whereNumber('task');
    });

    Route::middleware(['auth:sanctum', 'role:teacher'])->prefix('teacher')->group(function () {
        Route::get('answers', [ReviewController::class, 'index']);
        Route::put('answers/{answer}', [ReviewController::class, 'update'])->whereNumber('answer');
        Route::get('groups', [ReviewController::class, 'groups']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('tasks', [ContentController::class, 'tasks']);
        Route::put('tasks/{task}', [ContentController::class, 'updateTask'])->whereNumber('task');
        Route::post('tasks/{task}/audio', [ContentController::class, 'uploadAudio'])->whereNumber('task');
        Route::delete('tasks/{task}/audio', [ContentController::class, 'deleteAudio'])->whereNumber('task');

        Route::apiResource('users', UserController::class)->except('show');
        Route::get('groups', [UserController::class, 'groups']);
        Route::post('groups', [UserController::class, 'storeGroup']);
        Route::put('groups/{group}', [UserController::class, 'updateGroup'])->whereNumber('group');
        Route::delete('groups/{group}', [UserController::class, 'destroyGroup'])->whereNumber('group');
    });
});
