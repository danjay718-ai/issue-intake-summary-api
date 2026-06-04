<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Claude implementation for the summary generator chain.
 */
class AnthropicGenerator extends AbstractLlmGenerator
{
    public function providerName(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Call Anthropic Messages API and convert the JSON text response into the standard result.
     */
    public function generate(Issue $issue): array
    {
        $response = Http::withToken(config('services.anthropic.key'))
            ->withHeaders(['anthropic-version' => '2023-06-01'])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 500,
                'messages' => [
                    ['role' => 'user', 'content' => $this->prompt($issue)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic request failed: '.$response->body());
        }

        $content = $response->json('content.0.text');

        if (! is_string($content)) {
            throw new RuntimeException('Anthropic response was missing content.');
        }

        return $this->parseJson($content);
    }
}
