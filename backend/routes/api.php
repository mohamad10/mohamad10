<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatAdminController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/site', [SiteController::class, 'show']);
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
Route::post('/chat', [ChatController::class, 'send'])->middleware('throttle:15,1');
Route::get('/chat/{session}', [ChatController::class, 'history'])->whereUuid('session');
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

    Route::get('/admin/ai', [AiController::class, 'settings']);
    Route::put('/admin/ai', [AiController::class, 'update']);
    Route::post('/admin/ai/test', [AiController::class, 'test'])->middleware('throttle:20,1');
    Route::get('/admin/ai/models/{provider}', [AiController::class, 'models']);
    Route::post('/admin/ai/assist', [AiController::class, 'assist'])->middleware('throttle:60,1');
    Route::post('/admin/ai/translate-missing', [AiController::class, 'translateMissing'])->middleware('throttle:10,1');

    Route::get('/admin/chats', [ChatAdminController::class, 'index']);
    Route::get('/admin/chats/{session}', [ChatAdminController::class, 'show']);
    Route::delete('/admin/chats/{session}', [ChatAdminController::class, 'destroy']);
});
