<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\SummaryGenerationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    public function test_get_summary_logs_for_issue(): void
    {
        $issue = Issue::factory()->create(['title' => 'Issue A']);
        
        SummaryGenerationLog::create([
            'issue_id' => $issue->id,
            'provider' => 'gemini',
            'status' => 'failed',
            'prompt' => 'Support issue A prompt',
            'error_message' => 'API Key invalid',
            'duration_ms' => 120,
        ]);

        SummaryGenerationLog::create([
            'issue_id' => $issue->id,
            'provider' => 'rules_based',
            'status' => 'success',
            'prompt' => null,
            'response' => json_encode(['summary' => 'Summary A', 'suggested_next_action' => 'Action A']),
            'duration_ms' => 10,
        ]);

        $response = $this->getJson("/api/v1/issues/{$issue->id}/summary-logs");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.issue_id', $issue->id)
            ->assertJsonPath('data.issue_title', 'Issue A')
            ->assertJsonCount(2, 'data.logs')
            ->assertJsonPath('data.logs.0.provider', 'rules_based')
            ->assertJsonPath('data.logs.0.status', 'success')
            ->assertJsonPath('data.logs.0.response.summary', 'Summary A')
            ->assertJsonPath('data.logs.1.provider', 'gemini')
            ->assertJsonPath('data.logs.1.status', 'failed')
            ->assertJsonPath('data.logs.1.error_message', 'API Key invalid');
    }

    public function test_get_global_summary_logs(): void
    {
        $issue1 = Issue::factory()->create(['title' => 'Issue 1']);
        $issue2 = Issue::factory()->create(['title' => 'Issue 2']);

        SummaryGenerationLog::create([
            'issue_id' => $issue1->id,
            'provider' => 'gemini',
            'status' => 'success',
            'prompt' => 'Prompt 1',
            'response' => json_encode(['summary' => 'Summary 1', 'suggested_next_action' => 'Action 1']),
            'duration_ms' => 1500,
        ]);

        SummaryGenerationLog::create([
            'issue_id' => $issue2->id,
            'provider' => 'rules_based',
            'status' => 'success',
            'prompt' => null,
            'response' => json_encode(['summary' => 'Summary 2', 'suggested_next_action' => 'Action 2']),
            'duration_ms' => 5,
        ]);

        // No filters
        $response = $this->getJson('/api/v1/summary-logs');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data');

        // Filter by provider
        $response = $this->getJson('/api/v1/summary-logs?provider=gemini');
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.provider', 'gemini')
            ->assertJsonPath('data.data.0.issue_title', 'Issue 1');

        // Filter by status
        $response = $this->getJson('/api/v1/summary-logs?status=failed');
        $response->assertOk()
            ->assertJsonCount(0, 'data.data');
    }
}
