<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI implementation for the summary generator chain.
 */
class OpenAIGenerator extends AbstractLlmGenerator
{
    public function providerName(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * Call OpenAI chat completions and parse the required JSON object response.
     */
    public function generate(Issue $issue): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'user', 'content' => $this->prompt($issue)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('OpenAI response was missing content.');
        }

        return $this->parseJson($content);
    }
}
