<?php

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
