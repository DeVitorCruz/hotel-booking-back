<?php

namespace App\Models;

use App\Contracts\Payable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A guest's stay in one room.
 *
 * status flow:
 *   pending (hold, waiting for PayPal) → confirmed → checked_in → checked_out
 *   pending → expired      (hold timed out, nights released)
 *   pending|confirmed → cancelled
 *   confirmed → no_show
 */
class Booking extends Model implements Payable
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_NO_SHOW = 'no_show';

    /** Statuses that keep the room nights occupied. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN,
    ];

    /** Allowed staff transitions (from => [to, ...]). */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_CONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_CHECKED_IN => [self::STATUS_CHECKED_OUT],
    ];

    protected $fillable = [
        'reference', 'manage_token', 'user_id', 'room_type_id', 'room_id',
        'guest_name', 'guest_email', 'guest_phone', 'adults', 'children', 'special_requests',
        'check_in', 'check_out', 'nights',
        'currency', 'nightly_rates', 'subtotal', 'taxes', 'total', 'amount_due_now',
        'status', 'payment_method', 'source',
        'hold_expires_at', 'confirmed_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $hidden = ['manage_token'];

    protected $casts = [
        'manage_token' => 'encrypted',
        'check_in' => 'date',
        'check_out' => 'date',
        'nightly_rates' => 'array',
        'subtotal' => 'decimal:2',
        'taxes' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_due_now' => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function nightsReserved(): HasMany
    {
        return $this->hasMany(RoomNight::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function scopeExpiredHolds(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now());
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isHoldExpired(): bool
    {
        return $this->isPending() && $this->hold_expires_at !== null && $this->hold_expires_at->isPast();
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function matchesManageToken(?string $token): bool
    {
        return $token !== null && $token !== '' && hash_equals((string) $this->manage_token, $token);
    }

    // --- Payable --------------------------------------------------------

    public function payableReference(): string
    {
        return $this->reference;
    }

    public function payableAmount(): string
    {
        return number_format((float) $this->amount_due_now, 2, '.', '');
    }

    public function payableCurrency(): string
    {
        return $this->currency;
    }

    public function payableDescription(): string
    {
        $type = $this->roomType?->name ?? 'Room';

        return mb_substr(sprintf(
            '%s, %d night(s) from %s',
            $type, $this->nights, $this->check_in->toDateString()
        ), 0, 127);
    }

    public function payerName(): ?string
    {
        return $this->guest_name;
    }

    public function payerEmail(): ?string
    {
        return $this->guest_email;
    }
}
