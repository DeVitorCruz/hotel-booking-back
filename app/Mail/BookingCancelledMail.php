<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking cancelled: {$this->booking->reference} — " . app('hotel.settings')->get('name'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-cancelled', with: [
            'booking' => $this->booking->loadMissing(['roomType', 'payments']),
            'hotel' => app('hotel.settings')->all(),
        ]);
    }
}
