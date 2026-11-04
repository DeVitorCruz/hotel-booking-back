<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $connection = 'mercatura';

    /**
     * Extended user profile - personal info, address, social links.
     * 
     * Created lazily on first update (not on registration).
     * One-to-one with User.
     * 
     * @property int         $id
     * @property int         $user_id
     * @property string|null $avatar
     * @property string|null $bio
     * @property string|null $phone
     * @property string|null $address_line1
     * @property string|null $address_line2
     * @property string|null $city
     * @property string|null $state
     * @property string|null $postal_code
     * @property string      $country 
     * @property string|null $website
     * @property string|null $linkedin
     * @property string|null $twitter
     * @property string|null $instagram
     */
    protected $fillable = [
        'user_id',
        'avatar',
        'bio',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
        'linkedin',
        'twitter',
        'instagram',
   ];  

   /**
    * The user this profile belongs to.
    * 
    * @return BelongsTo<User, UserProfile>
    */
   public function user(): BelongsTo
   {
       return $this->belongsTo(User::class);
   }

   /**
    * Check if this profile has a complete address.
    */
   public function hasAddress(): bool
   {
       return !empty($this->address_line1)
           && !empty($this->city)
           && !empty($this->postal_code);
   }
}
