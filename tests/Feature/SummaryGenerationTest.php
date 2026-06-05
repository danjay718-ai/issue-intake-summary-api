<?php

namespace Tests\Feature;

use App\Jobs\GenerateSummaryJob;
use App\Models\Issue;
use App\Models\SummaryGenerationLog;
use App\Models\User;
use App\Services\Summary\RulesBasedGenerator;
use App\Services\Summary\SummaryGeneratorChain;
use App\Services\Summary\SummaryGeneratorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SummaryGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticate before every test — all issue routes now require a Sanctum token.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    public function test_running_job_populates_summary_fields_and_log(): void
    {
        $issue = Issue::factory()->create([
            'description'    => 'Order search is slow and timing out.',
            'category'       => 'performance',
            'summary_status' => 'pending',
        ]);

        GenerateSummaryJob::dispatchSync($issue);

        $issue->refresh();

        $this->assertSame('ready', $issue->summary_status);
        $this->assertNotEmpty($issue->summary);
        $this->assertNotEmpty($issue->suggested_next_action);
        $this->assertDatabaseHas('summary_generation_logs', [
            'issue_id' => $issue->id,
            'provider' => 'rules_based',
            'status'   => 'success',
        ]);
    }

    public function test_fallback_chain_logs_failure_then_tries_next_provider(): void
    {
        $issue = Issue::factory()->create();
        $chain = new SummaryGeneratorChain([
            new class implements SummaryGeneratorInterface
            {
                public function providerName(): string
                {
                    return 'anthropic';
                }

                public function isConfigured(): bool
                {
                    return true;
                }

                public function prompt(Issue $issue): ?string
                {
                    return 'prompt';
                }

                public function generate(Issue $issue): array
                {
                    throw new RuntimeException('Provider unavailable');
                }
            },
            new RulesBasedGenerator,
        ]);

        $result = $chain->generate($issue);

        $this->assertNotEmpty($result['summary']);
        $this->assertSame(2, SummaryGenerationLog::count());
        $this->assertDatabaseHas('summary_generation_logs', [
            'provider' => 'anthropic',
            'status'   => 'failed',
        ]);
        $this->assertDatabaseHas('summary_generation_logs', [
            'provider' => 'rules_based',
            'status'   => 'success',
        ]);
    }
}
