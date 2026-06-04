<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\GenerateSummaryJob;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles the versioned issue API: listing, creation, viewing, and partial updates.
 */
class IssueController extends Controller
{
    use ApiResponse;

    /**
     * Return a paginated issue list with optional combinable filters.
     */
    public function index(Request $request): JsonResponse
    {
        $issues = Issue::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->success('Issues retrieved', $issues);
    }

    /**
     * Persist a new issue immediately, then dispatch summary generation asynchronously.
     */
    public function store(StoreIssueRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = 'open';
        $data['summary_status'] = 'pending';
        $data['needs_attention'] = $data['priority'] === 'high';

        $issue = Issue::create($data);

        GenerateSummaryJob::dispatch($issue);

        return $this->success('Issue created', $issue->fresh(), 201);
    }

    /**
     * Return one issue with comments eager loaded to avoid N+1 queries.
     */
    public function show(Issue $issue): JsonResponse
    {
        return $this->success('Issue retrieved', $issue->load('comments'));
    }

    /**
     * Apply partial updates and regenerate the summary only when description changes.
     */
    public function update(UpdateIssueRequest $request, Issue $issue): JsonResponse
    {
        $data = $request->validated();
        $descriptionChanged = array_key_exists('description', $data)
            && $data['description'] !== $issue->description;

        if (array_key_exists('priority', $data)) {
            $data['needs_attention'] = $data['priority'] === 'high';
        }

        if ($descriptionChanged) {
            $data['summary'] = null;
            $data['suggested_next_action'] = null;
            $data['summary_status'] = 'pending';
        }

        $issue->update($data);

        if ($descriptionChanged) {
            GenerateSummaryJob::dispatch($issue->fresh());
        }

        return $this->success('Issue updated', $issue->fresh());
    }
}
