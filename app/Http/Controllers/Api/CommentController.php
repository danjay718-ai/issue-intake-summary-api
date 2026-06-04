<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;

/**
 * Handles comment creation for existing issues.
 */
class CommentController extends Controller
{
    use ApiResponse;

    /**
     * Store an immutable comment under the issue resolved from the route.
     */
    public function store(StoreCommentRequest $request, Issue $issue): JsonResponse
    {
        $comment = $issue->comments()->create($request->validated());

        return $this->success('Comment created', $comment, 201);
    }
}
