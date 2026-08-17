<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\ReflectionController;

Route::post('/reflections', [ReflectionController::class, 'store']);
Route::get('/reflections', [ReflectionController::class, 'index']);

use App\Http\Controllers\AssessmentController;

Route::post('/assessments', [AssessmentController::class, 'store']);