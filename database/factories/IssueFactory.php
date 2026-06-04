<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates realistic issue records for feature tests.
 */
class IssueFactory extends Factory
{
    /**
     * Default issue state mirrors the production creation defaults.
     */
    public function definition(): array
    {
        $priority = fake()->randomElement(['low', 'medium', 'high']);

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'priority' => $priority,
            'category' => fake()->randomElement(['billing', 'bug', 'feature-request', 'access', 'performance']),
            'status' => fake()->randomElement(['open', 'in_progress', 'resolved']),
            'summary_status' => 'pending',
            'needs_attention' => $priority === 'high',
        ];
    }
}
