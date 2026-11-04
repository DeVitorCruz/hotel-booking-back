<x-mail::message>
# Your stay is confirmed

Hi {{ $booking->guest_name }}, thank you for booking with **{{ $hotel['name'] }}**.

<x-mail::panel>
**Reference:** {{ $booking->reference }}<br>
**Room:** {{ $booking->roomType->name }}<br>
**Check-in:** {{ $booking->check_in->format('D, d M Y') }} from {{ $hotel['check_in_time'] }}<br>
**Check-out:** {{ $booking->check_out->format('D, d M Y') }} until {{ $hotel['check_out_time'] }}<br>
**Guests:** {{ $booking->adults }} adult(s){{ $booking->children ? ', ' . $booking->children . ' child(ren)' : '' }}
</x-mail::panel>

<x-mail::table>
| | |
|:--|--:|
| {{ $booking->nights }} night(s) | {{ $booking->currency }} {{ $booking->subtotal }} |
@if ((float) $booking->taxes > 0)
| Taxes | {{ $booking->currency }} {{ $booking->taxes }} |
@endif
| **Total** | **{{ $booking->currency }} {{ $booking->total }}** |
| Paid online | {{ $booking->currency }} {{ number_format((float) $booking->payments->where('status', 'paid')->sum('amount'), 2, '.', '') }} |
</x-mail::table>

@if ($booking->payment_method === 'pay_at_hotel')
You will pay at the front desk on arrival.
@endif

<x-mail::button :url="$manageUrl">
View or cancel booking
</x-mail::button>

Free cancellation until {{ $hotel['cancellation_hours'] }} hours before check-in.

@if (!empty($hotel['address']))
{{ $hotel['address'] }}<br>
@endif
@if (!empty($hotel['phone']))
{{ $hotel['phone'] }}<br>
@endif
{{ $hotel['email'] }}
</x-mail::message>
