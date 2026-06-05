<?php

namespace Tests\Feature;

use App\Jobs\GenerateSummaryJob;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IssueApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticate as a user before every test in this class.
     * Required now that all issue and comment routes are behind auth:sanctum.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    public function test_valid_issue_create_returns_pending_issue_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/issues', $this->issuePayload([
            'priority' => 'high',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.summary_status', 'pending')
            ->assertJsonPath('data.summary', null)
            ->assertJsonPath('data.needs_attention', true);

        $this->assertDatabaseHas('issues', ['title' => 'Checkout fails']);
        Queue::assertPushed(GenerateSummaryJob::class);
    }

    public function test_issue_create_validation_failure_returns_422_and_saves_nothing(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/issues', [
            'title'       => '   ',
            'description' => '',
            'priority'    => 'urgent',
            'category'    => ' ',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors' => ['title', 'description', 'priority', 'category']]);

        $this->assertDatabaseCount('issues', 0);
        Queue::assertNothingPushed();
    }

    public function test_issue_list_combines_status_and_priority_filters(): void
    {
        Issue::factory()->create(['status' => 'open', 'priority' => 'high', 'title' => 'Match']);
        Issue::factory()->create(['status' => 'open', 'priority' => 'low', 'title' => 'Wrong priority']);
        Issue::factory()->create(['status' => 'resolved', 'priority' => 'high', 'title' => 'Wrong status']);

        $response = $this->getJson('/api/v1/issues?status=open&priority=high');

        $response->assertOk()
            ->assertJsonPath('data.data.0.title', 'Match')
            ->assertJsonCount(1, 'data.data');
    }

    public function test_comment_can_be_added_to_existing_issue(): void
    {
        $issue = Issue::factory()->create();

        $response = $this->postJson("/api/v1/issues/{$issue->id}/comments", [
            'author_name' => ' Dana ',
            'body'        => ' Please check the payment logs. ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.author_name', 'Dana');

        $this->assertDatabaseHas('comments', [
            'issue_id'    => $issue->id,
            'author_name' => 'Dana',
            'body'        => 'Please check the payment logs.',
        ]);
    }

    public function test_single_issue_view_eager_loads_comments_without_n_plus_one(): void
    {
        $issue = Issue::factory()->create();
        Comment::factory()->count(5)->create(['issue_id' => $issue->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson("/api/v1/issues/{$issue->id}");

        $response->assertOk()
            ->assertJsonCount(5, 'data.comments');

        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_description_update_retriggers_job_and_resets_summary(): void
    {
        Queue::fake();
        $issue = Issue::factory()->create([
            'summary'              => 'Old summary',
            'suggested_next_action' => 'Old action',
            'summary_status'       => 'ready',
        ]);

        $response = $this->patchJson("/api/v1/issues/{$issue->id}", [
            'description' => 'New description with an error.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary', null)
            ->assertJsonPath('data.suggested_next_action', null)
            ->assertJsonPath('data.summary_status', 'pending');

        Queue::assertPushed(GenerateSummaryJob::class);
    }

    public function test_status_update_does_not_retrigger_job(): void
    {
        Queue::fake();
        $issue = Issue::factory()->create(['status' => 'open']);

        $response = $this->patchJson("/api/v1/issues/{$issue->id}", [
            'status' => 'resolved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        Queue::assertNothingPushed();
    }

    public function test_priority_controls_needs_attention_on_create_and_update(): void
    {
        Queue::fake();

        $high = $this->postJson('/api/v1/issues', $this->issuePayload(['priority' => 'high']));
        $low  = $this->postJson('/api/v1/issues', $this->issuePayload(['priority' => 'low']));

        $high->assertJsonPath('data.needs_attention', true);
        $low->assertJsonPath('data.needs_attention', false);

        $issue = Issue::factory()->create(['priority' => 'low', 'needs_attention' => false]);

        $this->patchJson("/api/v1/issues/{$issue->id}", ['priority' => 'high'])
            ->assertJsonPath('data.needs_attention', true);
    }

    private function issuePayload(array $overrides = []): array
    {
        return $overrides + [
            'title'       => 'Checkout fails',
            'description' => 'The checkout page shows an error after payment.',
            'priority'    => 'medium',
            'category'    => 'bug',
        ];
    }
}
