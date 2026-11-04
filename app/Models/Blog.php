<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Blog post - authored by users with editor/admin/owner role.
 * 
 * status: draft | published
 * slug: auto-generated from title on create
 */
class Blog extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image',
        'status',
        'published_at'
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Set the blog slug  
     */
    protected static function booted(): void 
    {
        static::creating(function ($blog) {
            if (empty($blog->slug)) {
                $blog->slug = Str::slug($blog->title);
            }
        });
    }

    /**
     * Get the user that owns the blog. 
     */
    public function author(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if the post is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if the post is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
