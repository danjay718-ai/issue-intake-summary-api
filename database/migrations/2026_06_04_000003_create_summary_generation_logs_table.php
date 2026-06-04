<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create append-only logs for summary provider attempts.
     */
    public function up(): void
    {
        Schema::create('summary_generation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->enum('status', ['success', 'failed']);
            $table->text('prompt')->nullable();
            $table->text('response')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Remove generation logs on rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('summary_generation_logs');
    }
};
