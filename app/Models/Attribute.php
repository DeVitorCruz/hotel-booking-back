<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $connection = 'tenant';

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * 
     * @var array<int, string> 
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the attribute values for the attribute.
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
