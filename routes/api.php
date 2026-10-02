<?php

use App\Http\Controllers\JournalExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReflectionController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\EvidenceController;

// Public - no account needed yet
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Sprint 5 - everything below requires a valid Bearer token.
// Send it as: Authorization: Bearer <token from /login or /register>

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/reflections', [ReflectionController::class, 'store']);
    Route::get('/reflections', [ReflectionController::class, 'index']);
    Route::get('/reflections/{id}', [ReflectionController::class, 'show']);
    Route::put('/reflections/{id}', [ReflectionController::class, 'update']);
    Route::delete('/reflections/{id}', [ReflectionController::class, 'destroy']);
    
  Route::get('/journal/export', [
    JournalExportController::class,
    'export'
]);

    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::get('/assessments', [AssessmentController::class, 'index']);
    Route::get('/assessments/{id}', [AssessmentController::class, 'show']);

    Route::post('/reflections/{id}/evidence', [EvidenceController::class, 'store']);
    Route::get('/reflections/{id}/evidence', [EvidenceController::class, 'index']);
    Route::get('/evidence/{id}/download', [EvidenceController::class, 'download']);
    Route::delete('/evidence/{id}', [EvidenceController::class, 'destroy']);
});

