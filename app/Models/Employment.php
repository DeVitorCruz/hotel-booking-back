<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employment record - tracks hiring relationships.
 * 
 * employer_id: the user who hired (owner, admin, or seller)
 * employee_id: the user who was hired
 * seller_id:   null = platform-level | set = store-level employment
 * role_name:   mirrors the Spatie role assigned on hire
 * status:      active | suspended | terminated
 */
class Employment extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'employer_id',
        'employee_id',
        'seller_id',
        'role_name',
        'hired_at',
        'fired_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'hired_at' => 'datetime',
        'fired_at' => 'datetime',
    ];
   
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
  
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && is_null($this->fired_at); 
    }

    public function isPlatformLevel(): bool
    {
        return is_null($this->seller_id);
    }
 
    public function isStoreLevel(): bool
    {
        return !is_null($this->seller_id);
    }
}
