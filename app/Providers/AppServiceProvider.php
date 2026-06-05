<?php

namespace App\Providers;

use App\Services\Summary\AnthropicGenerator;
use App\Services\Summary\GeminiGenerator;
use App\Services\Summary\OpenAIGenerator;
use App\Services\Summary\RulesBasedGenerator;
use App\Services\Summary\SummaryGeneratorChain;
use Illuminate\Support\ServiceProvider;

/**
 * Registers application services that need custom construction.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Gemini is first since it is the active LLM key; Anthropic and OpenAI
        // activate automatically once their keys are added to .env.
        $this->app->bind(SummaryGeneratorChain::class, function ($app) {
            return new SummaryGeneratorChain([
                $app->make(GeminiGenerator::class),
                $app->make(AnthropicGenerator::class),
                $app->make(OpenAIGenerator::class),
                $app->make(RulesBasedGenerator::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
