<?php

namespace Database\Seeders;

use App\Models\Comment;
use Illuminate\Database\Seeder;

/**
 * Seeds comments against the known issue IDs created by IssueSeeder.
 */
class CommentSeeder extends Seeder
{
    /**
     * Insert sample team comments for the seeded issues.
     */
    public function run(): void
    {
        $comments = [
            ['issue_id' => 1, 'author_name' => 'Ava', 'body' => 'Customer attached the invoice PDF.'],
            ['issue_id' => 1, 'author_name' => 'Noah', 'body' => 'Billing export shows a different subtotal.'],
            ['issue_id' => 2, 'author_name' => 'Mia', 'body' => 'Reproduced with code SUMMER20.'],
            ['issue_id' => 2, 'author_name' => 'Leo', 'body' => 'Logs show a null discount object.'],
            ['issue_id' => 3, 'author_name' => 'Ivy', 'body' => 'Product team asked for workflow details.'],
        ];

        foreach ($comments as $comment) {
            Comment::create($comment);
        }
    }
}
