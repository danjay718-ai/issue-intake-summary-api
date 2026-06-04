<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Issue is the main operational ticket record submitted through the API.
 */
class Issue extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'category',
        'status',
        'summary',
        'suggested_next_action',
        'summary_status',
        'needs_attention',
    ];

    protected function casts(): array
    {
        return [
            'needs_attention' => 'boolean',
        ];
    }

    /**
     * Comments are loaded on the single issue view, not the paginated list.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
