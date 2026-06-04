<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the main issue table with triage, summary, and soft-delete fields.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high']);
            $table->string('category');
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->text('summary')->nullable();
            $table->text('suggested_next_action')->nullable();
            $table->enum('summary_status', ['pending', 'ready', 'failed'])->default('pending');
            $table->boolean('needs_attention')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Drop the issue table when rolling back this migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
