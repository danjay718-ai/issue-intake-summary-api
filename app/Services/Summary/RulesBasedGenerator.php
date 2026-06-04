<?php

namespace App\Services\Summary;

use App\Models\Issue;

/**
 * Offline deterministic fallback that produces a useful summary without external APIs.
 */
class RulesBasedGenerator implements SummaryGeneratorInterface
{
    public function providerName(): string
    {
        return 'rules_based';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function prompt(Issue $issue): ?string
    {
        return null;
    }

    /**
     * Build a concise summary from issue category, priority, and description.
     */
    public function generate(Issue $issue): array
    {
        $description = strtolower($issue->description);
        $category = strtolower($issue->category);

        $categoryLabels = [
            'billing' => 'billing issue',
            'bug' => 'software defect',
            'feature-request' => 'feature request',
            'access' => 'access issue',
            'performance' => 'performance issue',
        ];

        $summaryType = $categoryLabels[$category] ?? $category.' issue';
        $urgency = $issue->priority === 'high'
            ? 'This high-priority'
            : ($issue->priority === 'low' ? 'This routine' : 'This');

        return [
            'summary' => sprintf(
                '%s %s reports: %s',
                $urgency,
                $summaryType,
                rtrim($issue->description, '.')
            ),
            'suggested_next_action' => $this->keywordAction($description, $category),
        ];
    }

    /**
     * Choose the most concrete next action from keywords before falling back to category.
     */
    private function keywordAction(string $description, string $category): string
    {
        if (str_contains($description, 'crash') || str_contains($description, 'error')) {
            return 'Ask engineering to reproduce the failure and review the latest application logs.';
        }

        if (str_contains($description, 'unable') || str_contains($description, 'access denied')) {
            return 'Verify the affected user permissions and confirm the expected access level.';
        }

        if (str_contains($description, 'slow') || str_contains($description, 'timeout')) {
            return 'Check recent performance metrics and identify the slowest failing request path.';
        }

        return match ($category) {
            'billing' => 'Have the billing team review the customer account and recent payment events.',
            'bug' => 'Create a reproducible bug report and assign it to engineering for triage.',
            'feature-request' => 'Capture the requested workflow and add it to product triage.',
            'access' => 'Confirm the requester identity and update permissions if approved.',
            'performance' => 'Review service metrics and isolate the highest-latency dependency.',
            default => 'Assign the issue to the appropriate owner for initial triage.',
        };
    }
}
