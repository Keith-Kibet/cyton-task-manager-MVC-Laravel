<?php

/**
 * JSON API routes — prefix `/api` (see bootstrap/app.php).
 *
 * Public: register, login. All protected routes use `jwt` middleware (Bearer JWT).
 * Admin routes add `admin` middleware (role === admin).
 */

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function () {
    // Report must be registered before /tasks/{task} so "report" is not captured as an id.
    Route::get('/tasks/report', [TaskController::class, 'report']);
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    // Restore / force use explicit UUID id because route model binding would not resolve only-trashed rows.
    Route::post('/tasks/{id}/restore', [TaskController::class, 'restore'])->whereUuid('id');
    Route::delete('/tasks/{id}/force', [TaskController::class, 'forceDestroy'])->whereUuid('id');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::patch('/admin/users/{user}/role', [AdminUserController::class, 'updateRole']);
    });
});
