<?php

namespace Database\Seeders;

use App\Models\Issue;
use Illuminate\Database\Seeder;

/**
 * Seeds review-friendly issue examples across priority, status, and category.
 */
class IssueSeeder extends Seeder
{
    /**
     * Insert deterministic issue examples with correct attention defaults.
     */
    public function run(): void
    {
        $issues = [
            [
                'title' => 'Incorrect invoice total',
                'description' => 'Customer reports an error on the latest invoice total.',
                'priority' => 'high',
                'category' => 'billing',
                'status' => 'open',
            ],
            [
                'title' => 'Checkout crashes',
                'description' => 'Checkout page crashes when applying a discount code.',
                'priority' => 'high',
                'category' => 'bug',
                'status' => 'in_progress',
            ],
            [
                'title' => 'Bulk export request',
                'description' => 'Team would like a bulk export option for reports.',
                'priority' => 'medium',
                'category' => 'feature-request',
                'status' => 'open',
            ],
            [
                'title' => 'Warehouse access restored',
                'description' => 'User was unable to access the warehouse dashboard.',
                'priority' => 'low',
                'category' => 'access',
                'status' => 'resolved',
            ],
            [
                'title' => 'Slow order search',
                'description' => 'Order search is slow during peak hours.',
                'priority' => 'medium',
                'category' => 'performance',
                'status' => 'in_progress',
            ],
        ];

        foreach ($issues as $issue) {
            Issue::create($issue + [
                'summary_status' => 'pending',
                'needs_attention' => $issue['priority'] === 'high',
            ]);
        }
    }
}
