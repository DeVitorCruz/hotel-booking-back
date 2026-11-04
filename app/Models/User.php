<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Application user: hotel staff (owner, admin, receptionist, editor)
 * or a registered guest. Guests can also book without an account.
 *
 * Roles are managed via Spatie Laravel Permission.
 * API authentication is handled via Laravel Sanctum tokens.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string|null $phone
 * @property string|null $google_id
 * @property string|null $facebook_id
 * @property string|null $avatar_url
 * @property string|null $password
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /** Roles that can access the back office. */
    public const STAFF_ROLES = ['owner', 'admin', 'receptionist', 'editor'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'google_id',
        'facebook_id',
        'avatar_url',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Extended personal profile (avatar, bio, address, social links).
     *
     * @return HasOne<UserProfile>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Bookings made while logged in.
     *
     * @return HasMany<Booking>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Staff spotlight entry (public about page).
     *
     * @return HasOne<StaffSpotlight>
     */
    public function spotlight(): HasOne
    {
        return $this->hasOne(StaffSpotlight::class);
    }

    /**
     * Blog posts authored by this user.
     *
     * @return HasMany<Blog>
     */
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    /**
     * Check if the user belongs to the hotel staff.
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }
}
