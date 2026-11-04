<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $manageUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking confirmed: {$this->booking->reference} — " . app('hotel.settings')->get('name'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-confirmed', with: [
            'booking' => $this->booking->loadMissing(['roomType', 'payments']),
            'hotel' => app('hotel.settings')->all(),
            'manageUrl' => $this->manageUrl,
        ]);
    }
}
