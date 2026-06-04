<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comment is an immutable note attached to an issue.
 */
class Comment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'issue_id',
        'author_name',
        'body',
    ];

    protected static function booted(): void
    {
        // Comments intentionally store only created_at because they are not editable.
        static::creating(function (Comment $comment): void {
            $comment->created_at ??= now();
        });
    }

    /**
     * Each comment belongs to exactly one issue.
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
