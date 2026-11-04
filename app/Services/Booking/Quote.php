<?php

namespace App\Services\Booking;

/**
 * Price breakdown for a stay. All money is kept in integer cents internally
 * to avoid float rounding; toArray() renders decimal strings.
 */
final class Quote
{
    /**
     * @param array<int, array{date: string, cents: int}> $nightly
     */
    public function __construct(
        public readonly string $currency,
        public readonly array $nightly,
        public readonly int $subtotalCents,
        public readonly int $taxCents,
        public readonly int $totalCents,
        public readonly int $dueNowCents,
        public readonly int $minNights,
    ) {}

    public function nights(): int
    {
        return count($this->nightly);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public function nightlyRates(): array
    {
        return array_map(fn ($n) => ['date' => $n['date'], 'price' => self::money($n['cents'])], $this->nightly);
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'nights' => $this->nights(),
            'nightly_rates' => $this->nightlyRates(),
            'subtotal' => self::money($this->subtotalCents),
            'taxes' => self::money($this->taxCents),
            'total' => self::money($this->totalCents),
            'amount_due_now' => self::money($this->dueNowCents),
            'average_nightly' => self::money(intdiv($this->subtotalCents, max(1, $this->nights()))),
            'min_nights' => $this->minNights,
        ];
    }
}
