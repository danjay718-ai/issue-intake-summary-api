<?php

namespace App\Services\Summary;

use App\Models\Issue;

/**
 * Contract every summary provider must implement so the job can call all providers uniformly.
 */
interface SummaryGeneratorInterface
{
    /**
     * Stable provider identifier stored in summary_generation_logs.
     */
    public function providerName(): string;

    /**
     * Indicates whether required configuration, such as an API key, is available.
     */
    public function isConfigured(): bool;

    /**
     * Returns the rendered prompt when a provider uses one; rules-based generation returns null.
     */
    public function prompt(Issue $issue): ?string;

    /**
     * Generate the summary payload consumed by GenerateSummaryJob.
     */
    public function generate(Issue $issue): array;
}
