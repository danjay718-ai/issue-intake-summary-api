<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\SummaryLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // Public auth routes — no token required
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);

    // Protected routes — valid Sanctum token required for all routes below
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Version every public endpoint so future breaking API changes can live beside v1.
        Route::get('/issues',                       [IssueController::class, 'index']);
        Route::post('/issues',                      [IssueController::class, 'store']);
        Route::get('/issues/{issue}',               [IssueController::class, 'show']);
        Route::patch('/issues/{issue}',             [IssueController::class, 'update']);
        Route::post('/issues/{issue}/comments',     [CommentController::class, 'store']);
        Route::get('/issues/{issue}/summary-logs',  [SummaryLogController::class, 'forIssue']);
        Route::get('/summary-logs',                 [SummaryLogController::class, 'index']);
    });
});
