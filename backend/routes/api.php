<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/site', [SiteController::class, 'show']);
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Admin
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/password', [AuthController::class, 'password']);

    Route::put('/admin/site', [SiteController::class, 'update']);
    Route::post('/admin/uploads', [UploadController::class, 'store']);

    Route::get('/admin/messages', [ContactController::class, 'index']);
    Route::patch('/admin/messages/{message}/read', [ContactController::class, 'markRead']);
    Route::delete('/admin/messages/{message}', [ContactController::class, 'destroy']);
});
