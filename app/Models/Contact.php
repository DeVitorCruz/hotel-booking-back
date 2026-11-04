<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact form submission.
 * 
 * Authenticated users: linked via user_id, unlimited submissions.
 * Guests: user_id=null, single submission enforced by session_tokne + ip_address.
 * 
 * status: unread | read | replied
 */
class Contact extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'session_token',
        'ip_address',
        'name',
        'email',
        'subject',
        'message',
        'status',
    ];

    /**
     * Identify the user that is requesting
     */
    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Checking if the user is registered.
     */
    public function isGuest(): bool
    {
        return is_null($this->user_id);
    }
}
