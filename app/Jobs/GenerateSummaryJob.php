<?php

namespace App\Jobs;

use App\Models\Issue;
use App\Services\Summary\SummaryGeneratorChain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Queue job that generates an issue summary outside the HTTP request cycle.
 */
class GenerateSummaryJob implements ShouldQueue
{
    use Queueable;

    /**
     * Retry transient provider failures before the job is considered failed.
     */
    public int $tries = 3;

    /**
     * Bound provider calls so a worker cannot hang indefinitely.
     */
    public int $timeout = 60;

    public function __construct(public Issue $issue) {}

    /**
     * Run the fallback chain and persist the generated summary fields.
     */
    public function handle(SummaryGeneratorChain $chain): void
    {
        $issue = $this->issue->fresh();

        if (! $issue) {
            return;
        }

        $result = $chain->generate($issue);

        $issue->update([
            'summary' => $result['summary'],
            'suggested_next_action' => $result['suggested_next_action'],
            'summary_status' => 'ready',
        ]);
    }

    /**
     * Mark the issue as failed only after Laravel exhausts configured retries.
     */
    public function failed(?Throwable $exception): void
    {
        $this->issue->fresh()?->update([
            'summary_status' => 'failed',
        ]);
    }
}
