<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record for a single summary generation provider attempt.
 */
class SummaryGenerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'issue_id',
        'provider',
        'status',
        'prompt',
        'response',
        'error_message',
        'duration_ms',
    ];

    protected static function booted(): void
    {
        // Logs are append-only attempt records, so only created_at is maintained.
        static::creating(function (SummaryGenerationLog $log): void {
            $log->created_at ??= now();
        });
    }

    /**
     * Each generation attempt is tied back to the issue being summarized.
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
