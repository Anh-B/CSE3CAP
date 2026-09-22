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

Route::put('/reflections/{id}', [ReflectionController::class, 'update']);
Route::delete('/reflections/{id}', [ReflectionController::class, 'destroy']);

// Sprint 3 - read endpoints so the radar chart can load real self + assessor scores
Route::get('/reflections/{id}', [ReflectionController::class, 'show']);
Route::get('/assessments', [AssessmentController::class, 'index']);
Route::get('/assessments/{id}', [AssessmentController::class, 'show']);
