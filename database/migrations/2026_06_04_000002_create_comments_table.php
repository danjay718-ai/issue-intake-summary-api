<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create immutable comments linked to issues.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Drop comments before the issues table can be removed.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
