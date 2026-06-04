<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed issues first, then comments that reference those issue IDs.
     */
    public function run(): void
    {
        $this->call([
            IssueSeeder::class,
            CommentSeeder::class,
        ]);
    }
}
