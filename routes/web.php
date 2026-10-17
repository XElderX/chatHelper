<?php

use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WritingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('app'));
// Web middleware deliberately protects the JSON API with same-origin sessions and CSRF.
Route::prefix('api')->middleware('throttle:local-api')->group(function () {
    Route::get('writing/modes', [SettingsController::class, 'modes']);
    Route::get('writing/settings', [SettingsController::class, 'show']);
    Route::put('writing/settings', [SettingsController::class, 'update']);
    Route::middleware('throttle:writing')->group(function () {
        Route::post('writing/analyze', [WritingController::class, 'analyze']);
        Route::post('writing/rewrite', [WritingController::class, 'rewrite']);
        Route::post('writing/explain', [WritingController::class, 'explain']);
    });
    Route::apiResource('documents', DocumentController::class);
});

Route::get('/auth/chatgpt/callback', [ConnectionController::class, 'callback'])->middleware('throttle:20,1');
Route::prefix('api/connections')->middleware('throttle:30,1')->group(function () {
    Route::get('/', [ConnectionController::class, 'show']);
    Route::put('/', [ConnectionController::class, 'select']);
    Route::post('chatgpt/start', [ConnectionController::class, 'start']);
    Route::get('chatgpt/models', [ConnectionController::class, 'models']);
    Route::put('chatgpt/model', [ConnectionController::class, 'model']);
    Route::post('chatgpt/disconnect', [ConnectionController::class, 'disconnect']);
});
