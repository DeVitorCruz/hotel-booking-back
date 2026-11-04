<?php

namespace App\Services\Booking;

use App\Models\RateOverride;
use App\Models\RoomType;
use App\Services\Hotel\HotelSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Prices a stay night by night:
 *   price(night) = highest-priority rate override covering that date, else room type base price.
 * Taxes and the online deposit are applied on top, from hotel settings.
 */
class PriceCalculator
{
    public function __construct(private HotelSettings $settings) {}

    /**
     * @param string $paymentMethod paypal | pay_at_hotel (pay at hotel => nothing due now)
     */
    public function quote(RoomType $roomType, CarbonImmutable $checkIn, CarbonImmutable $checkOut, string $paymentMethod = 'paypal'): Quote
    {
        $overrides = RateOverride::query()
            ->where('room_type_id', $roomType->id)
            ->whereDate('start_date', '<', $checkOut->toDateString())
            ->whereDate('end_date', '>=', $checkIn->toDateString())
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $baseCents = self::toCents($roomType->base_price);
        $minNights = (int) $this->settings->get('min_nights', 1);
        $nightly = [];

        foreach (CarbonPeriod::create($checkIn, '1 day', $checkOut->subDay()) as $date) {
            $override = $overrides->first(
                fn (RateOverride $o) => $date->betweenIncluded($o->start_date->startOfDay(), $o->end_date->startOfDay())
            );

            if ($override?->min_nights) {
                $minNights = max($minNights, (int) $override->min_nights);
            }

            $nightly[] = [
                'date' => $date->toDateString(),
                'cents' => $override ? self::toCents($override->price) : $baseCents,
            ];
        }

        $subtotal = array_sum(array_column($nightly, 'cents'));
        $tax = (int) round($subtotal * ((float) $this->settings->get('tax_percent', 0)) / 100);
        $total = $subtotal + $tax;

        $depositPercent = min(100, max(0, (float) $this->settings->get('deposit_percent', 100)));
        $dueNow = $paymentMethod === 'pay_at_hotel' ? 0 : (int) round($total * $depositPercent / 100);

        return new Quote(
            currency: $this->settings->currency(),
            nightly: $nightly,
            subtotalCents: $subtotal,
            taxCents: $tax,
            totalCents: $total,
            dueNowCents: $dueNow,
            minNights: $minNights,
        );
    }

    public static function toCents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
