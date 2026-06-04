<?php

namespace Database\Factories;

use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates comments attached to generated issues for tests.
 */
class CommentFactory extends Factory
{
    /**
     * Default comment state includes an issue relationship.
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'author_name' => fake()->name(),
            'body' => fake()->sentence(10),
        ];
    }
}
