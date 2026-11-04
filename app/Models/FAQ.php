<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Frequently Asked Question.
 * 
 * Managed by admin/owner/editor.
 * Public read access - no auth required.
 */
class FAQ extends Model
{
    protected $connection = 'tenant';

    protected $table = 'faqs';

    protected $fillable = [
        'question',
        'answer',
        'category',
        'order',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
