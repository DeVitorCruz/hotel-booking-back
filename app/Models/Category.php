<?php
 namespace App\Models;

 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Factories\HasFactory;
 use Illuminate\Database\Eloquent\Relations\BelongsTo;
 use Illuminate\Database\Eloquent\Relations\HasMany;

 class Category extends Model
 {
    protected $connection = 'tenant';

    use HasFactory;

    protected $fillable = [
      'parent_id',
      'suggested_by',
      'approved_by',
      'name',
      'slug',
      'description',
      'image_path',
      'status',
      'is_active',
   ];

   protected $casts = [
      'is_active' => 'boolean',
   ];
  
   /** Parent category */
    public function parent(): BelongsTo
    {
      return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Direct children */
    public function children(): HasMany
    {
      return $this->hasMany(Category::class, 'parent_id');
    }
 
    /** All descendants recursively */
    public function allChildren(): HasMany 
    { 
      return $this->children()->with('allChildren');
    }

    /** User who suggested this category */
    public function suggestedBy(): BelongsTo
    {
      return $this->belongsTo(User::class, 'suggested_by');
    }

    /** Admin who approved/rejeted */
    public function approvedBy(): BelongsTo
    { 
      return $this->belongsTo(User::class, 'approved_by');
    }

    /** Products in this category */
    public function products(): HasMany
    {
      return $this->hasMany(Product::class);
    }
}
