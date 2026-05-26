<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
// COMMENTED: registration disabled
// use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WidgetChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// COMMENTED: registration disabled
// Route::post('/register', RegisteredUserController::class);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::middleware(['widget.request', 'throttle:widget-chat-create'])
    ->post('/widget/{widgetKey}/chats', [WidgetChatController::class, 'createSession']);
Route::middleware(['widget.request', 'throttle:widget-chat-message'])
    ->post('/widget/{widgetKey}/chats/message', [WidgetChatController::class, 'sendMessage']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::get('/stats', StatsController::class);

    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/projects/{project}/documents', [ProjectDocumentController::class, 'index']);
    Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store']);
    Route::delete('/projects/{project}/documents/{document}', [ProjectDocumentController::class, 'destroy']);

    Route::get('/projects/{project}/chats', [ProjectChatController::class, 'index']);
    Route::post('/projects/{project}/chats', [ProjectChatController::class, 'createSession']);
    Route::post('/projects/{project}/chats/message', [ProjectChatController::class, 'sendMessage']);
    Route::get('/projects/{project}/chats/{chat}/history', [ProjectChatController::class, 'history']);
});
