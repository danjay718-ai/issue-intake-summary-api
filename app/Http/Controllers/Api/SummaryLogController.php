<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Issue;
use App\Models\SummaryGenerationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes the append-only summary generation audit log for inspection and debugging.
 */
class SummaryLogController extends Controller
{
    use ApiResponse;

    /**
     * Return all generation attempts for a single issue, newest first.
     *
     * Useful for diagnosing which provider ran, whether it succeeded, how long
     * it took, and the exact error message if it failed.
     */
    public function forIssue(Issue $issue): JsonResponse
    {
        $logs = SummaryGenerationLog::where('issue_id', $issue->id)
            ->latest('id')
            ->get()
            ->map(fn (SummaryGenerationLog $log) => $this->formatLog($log));

        return $this->success('Summary generation logs retrieved', [
            'issue_id'   => $issue->id,
            'issue_title' => $issue->title,
            'logs'       => $logs,
        ]);
    }

    /**
     * Return a paginated global audit log, optionally filtered by provider or status.
     *
     * Query params:
     *   - provider  : e.g. "gemini", "rules_based", "anthropic", "openai"
     *   - status    : "success" | "failed"
     *   - per_page  : default 20
     */
    public function index(Request $request): JsonResponse
    {
        $logs = SummaryGenerationLog::query()
            ->with('issue:id,title,status,summary_status')
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->when($request->filled('status'),   fn ($q) => $q->where('status',   $request->string('status')))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        // Format each log entry inline so the response is immediately readable.
        $logs->getCollection()->transform(
            fn (SummaryGenerationLog $log) => $this->formatLog($log)
        );

        return $this->success('Summary generation audit log retrieved', $logs);
    }

    /**
     * Normalize a log row into a clean, human-readable shape.
     */
    private function formatLog(SummaryGenerationLog $log): array
    {
        return [
            'id'            => $log->id,
            'issue_id'      => $log->issue_id,
            'issue_title'   => $log->issue?->title,
            'provider'      => $log->provider,
            'status'        => $log->status,
            'duration_ms'   => $log->duration_ms,
            'created_at'    => $log->created_at?->toIso8601String(),
            // Only surface prompt/response/error when present to keep the list clean.
            'prompt'        => $log->prompt,
            'response'      => $log->response ? json_decode($log->response, true) : null,
            'error_message' => $log->error_message,
        ];
    }
}
