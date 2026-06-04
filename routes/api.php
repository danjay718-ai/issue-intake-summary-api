<?php

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\IssueController;
use Illuminate\Support\Facades\Route;

// Version every public endpoint so future breaking API changes can live beside v1.
Route::prefix('v1')->group(function (): void {
    Route::get('/issues', [IssueController::class, 'index']);
    Route::post('/issues', [IssueController::class, 'store']);
    Route::get('/issues/{issue}', [IssueController::class, 'show']);
    Route::patch('/issues/{issue}', [IssueController::class, 'update']);
    Route::post('/issues/{issue}/comments', [CommentController::class, 'store']);
});
