<?php

namespace App\Services\Summary;

use App\Models\Issue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini implementation for the summary generator chain.
 */
class GeminiGenerator extends AbstractLlmGenerator
{
    public function providerName(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Call Gemini generateContent and parse the JSON response text.
     */
    public function generate(Issue $issue): array
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            config('services.gemini.model')
        );

        $response = Http::timeout(30)
            ->post($url.'?key='.config('services.gemini.key'), [
                'contents' => [
                    ['parts' => [['text' => $this->prompt($issue)]]],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: '.$response->body());
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($content)) {
            throw new RuntimeException('Gemini response was missing content.');
        }

        return $this->parseJson($content);
    }
}
