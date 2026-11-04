<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;	

/**
 * Represents an application user.
 * 
 * A user can optionally become a seller by creating a seller profile.
 * Roles (admin, seller, buyer) are managed via Spatie Laravel Permission.
 * API authentication is handled via Laravel Sanctum tokens.
 * 
 * @property in          $id
 * @property string      $name
 * @property string      $email
 * @property string      $google_id
 * @property string|null $facebook_id
 * @property string|null $avatar_url
 * @property string|null $password
 * @property string|null $remember_token
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carban $created_at
 * @property \Carbon\Carbon $updated_at 
 */
class User extends Authenticatable
{
    protected $connection = 'mercatura';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'facebook_id',
        'avatar_url',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Exntend personal profile (avatar, bio, address, social links).
     * Created automatically on first profile update.
     * 
     * @return HasOne<UserProfile>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Seller store profile.
     * Exists only if user has applied and been approved as a seller.
     * 
     * @return HasOne<Seller>
     */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }
    
    /**
     * Active shopping cart.
     * Guest carts are merged into this cart on login.
     * 
     * @return HasOne<Cart>
     */
    public function cart(): HasOne
    {
		return $this->hasOne(Cart::class); 
	}
	
	/**
	 * All orders placed by the user.
	 * 
	 * @return HasMany<Order>
	 */
	public function orders(): HasMany
	{
		return $this->hasMany(Order::class);
	}

    /**
     * Wishlist belonging to this user.
     * 
     * @return HasOne<Wishlist>
     */
    public function wishlist(): HasOne 
    {
        return $this->hasOne(Wishlist::class);
    }

    /**
     * Users this user has hired (as employer).
     * 
     * @return HasMany<Employment>
     */
    public function hiredEmployees(): HasMany 
    {
        return $this->hasMany(Employment::class, 'employer_id');
    }

    /**
     * Employment records where this user is the employee.
     * 
     * @return HasMany<Employment>
     */
    public function employments(): HasMany 
    {
        return $this->hasMany(Employment::class, 'employee_id');
    }

    /**
     * Teams owned/created by this user.
     * 
     * @return HasMany<Team>
     */
    public function ownedTeams(): HasMany 
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Team memberships for this user (via pivot). 
     * 
     * @return HasMany<TeamMember>
     */
    public function teamMemberships(): HasMany 
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Staff spotlight entry (public about page).
     * Optinal - only if owner has added this user to the about page.
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
     * Check if user is platform staff.
     */
    public function isStaff(): bool 
    {
        return $this->hasAnyRole(['owner', 'admin', 'editor', 'employee']);
    }

    /**
     * Check if usr has an active approved seller store.
     */
    public function isSeller(): bool 
    {
        return $this->hasRole('seller') && $this->seller?->status === 'active';
    }

    /**
     * Check if user is a marketplace seller (can hire store employees).
     */
    public function isMarketplace(): bool 
    {
        return $this->isSeller() && (bool) $this->seller?->is_marketplace;
    }

    /**
     * Check if user has an active employment record.
     */
    public function isEmployed(): bool 
    {
        return $this->employments()->where('status', 'active')->exists();
    }
}
