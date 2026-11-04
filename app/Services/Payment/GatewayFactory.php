<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class GatewayFactory
{
    /** Gateway key => implementation. Adding a gateway = add it here + implement the interface. */
    public const GATEWAYS = [
        'paypal' => PayPalGateway::class,
        'pay_at_hotel' => PayAtHotelGateway::class,
    ];

    /**
     * Resolve a gateway through the container (so tests can swap implementations).
     */
    public static function make(string $gateway): PaymentGatewayInterface
    {
        $class = self::GATEWAYS[$gateway] ?? throw new InvalidArgumentException(
            "Unsupported payment gateway: [{$gateway}]. Supported: " . implode(', ', self::supported()) . '.'
        );

        return app($class);
    }

    /** @return list<string> */
    public static function supported(): array
    {
        return array_keys(self::GATEWAYS);
    }
}
