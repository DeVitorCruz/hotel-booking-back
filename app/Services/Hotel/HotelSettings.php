<?php

namespace App\Services\Hotel;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reads hotel settings: back-office overrides (settings table) first,
 * then config/hotel.php (.env) defaults.
 */
class HotelSettings
{
    private const CACHE_KEY = 'hotel.settings';

    /** Keys safe to expose on the public GET /hotel endpoint. */
    public const PUBLIC_KEYS = [
        'name', 'email', 'phone', 'address', 'timezone', 'currency',
        'check_in_time', 'check_out_time', 'tax_percent', 'deposit_percent',
        'min_nights', 'max_nights', 'max_advance_days', 'hold_minutes',
        'cancellation_hours', 'payment_methods',
    ];

    private ?array $overrides = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->overrides();

        return array_key_exists($key, $overrides)
            ? $overrides[$key]
            : config("hotel.{$key}", $default);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    public function all(): array
    {
        return array_merge(config('hotel', []), $this->overrides());
    }

    public function public(): array
    {
        return array_intersect_key($this->all(), array_flip(self::PUBLIC_KEYS));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->overrides = null;
    }

    public function currency(): string
    {
        return strtoupper((string) $this->get('currency', 'USD'));
    }

    /** Today's date in the hotel's time zone. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->get('timezone', 'UTC'))->startOfDay();
    }

    public function paymentMethodEnabled(string $method): bool
    {
        return in_array($method, (array) $this->get('payment_methods', []), true);
    }

    private function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        return $this->overrides = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });
    }
}
