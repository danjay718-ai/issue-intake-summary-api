<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Shared prompt rendering and response parsing for HTTP-based LLM providers.
 */
abstract class AbstractLlmGenerator implements SummaryGeneratorInterface
{
    /**
     * Render the committed prompt template with issue fields.
     */
    public function prompt(Issue $issue): string
    {
        $template = File::get(resource_path('prompts/summary.txt'));

        return strtr($template, [
            '{title}' => $issue->title,
            '{category}' => $issue->category,
            '{priority}' => $issue->priority,
            '{description}' => $issue->description,
        ]);
    }

    /**
     * Enforce the expected JSON response shape from provider text output.
     */
    protected function parseJson(string $content): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Provider response was not valid JSON.');
        }

        if (empty($decoded['summary']) || empty($decoded['suggested_next_action'])) {
            throw new RuntimeException('Provider response was missing required fields.');
        }

        return [
            'summary' => trim($decoded['summary']),
            'suggested_next_action' => trim($decoded['suggested_next_action']),
        ];
    }
}
