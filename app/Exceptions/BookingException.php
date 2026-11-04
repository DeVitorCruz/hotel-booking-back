<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Business-rule failure in the booking flow. Renders as JSON with a stable
 * machine-readable `code` the Angular app can switch on.
 */
class BookingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'booking_error',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function noAvailability(): self
    {
        return new self('No rooms of this type are available for the selected dates.', 'no_availability', 409);
    }

    public static function holdExpired(): self
    {
        return new self('This booking hold has expired. Please search again.', 'hold_expired', 409);
    }

    public static function invalidState(string $message): self
    {
        return new self($message, 'invalid_state', 409);
    }

    public static function payment(string $message): self
    {
        return new self($message, 'payment_failed', 402);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }
}
