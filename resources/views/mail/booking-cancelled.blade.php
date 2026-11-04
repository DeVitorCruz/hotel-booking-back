<x-mail::message>
# Booking cancelled

Hi {{ $booking->guest_name }}, your booking **{{ $booking->reference }}** at **{{ $hotel['name'] }}**
({{ $booking->roomType->name }}, {{ $booking->check_in->format('d M Y') }} – {{ $booking->check_out->format('d M Y') }}) has been cancelled.

@php($refunded = (float) $booking->payments->sum('refunded_amount'))
@if ($refunded > 0)
A refund of **{{ $booking->currency }} {{ number_format($refunded, 2, '.', '') }}** was issued to your PayPal account. It can take a few days to appear.
@endif

If this wasn't you, reply to this email or contact us at {{ $hotel['email'] }}.

Thanks,<br>
{{ $hotel['name'] }}
</x-mail::message>
