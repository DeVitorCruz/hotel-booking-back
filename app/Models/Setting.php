<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Back-office override for a config/hotel.php key. */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];
}
