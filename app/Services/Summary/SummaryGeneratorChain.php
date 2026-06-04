<?php

namespace App\Services\Summary;

use App\Models\Issue;
use App\Models\SummaryGenerationLog;
use Throwable;

/**
 * Attempts configured generators in priority order and logs every attempted outcome.
 */
class SummaryGeneratorChain
{
    public function __construct(private readonly array $generators) {}

    /**
     * Return the first successful provider result, falling back until one works.
     */
    public function generate(Issue $issue): array
    {
        $lastException = null;

        foreach ($this->generators as $generator) {
            if (! $generator->isConfigured()) {
                continue;
            }

            $startedAt = microtime(true);
            $prompt = $generator->prompt($issue);

            try {
                $result = $generator->generate($issue);

                SummaryGenerationLog::create([
                    'issue_id' => $issue->id,
                    'provider' => $generator->providerName(),
                    'status' => 'success',
                    'prompt' => $prompt,
                    'response' => json_encode($result),
                    'duration_ms' => $this->durationMs($startedAt),
                ]);

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;

                SummaryGenerationLog::create([
                    'issue_id' => $issue->id,
                    'provider' => $generator->providerName(),
                    'status' => 'failed',
                    'prompt' => $prompt,
                    'error_message' => $exception->getMessage(),
                    'duration_ms' => $this->durationMs($startedAt),
                ]);
            }
        }

        throw $lastException;
    }

    /**
     * Convert a microtime start point into a whole millisecond duration for logs.
     */
    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
