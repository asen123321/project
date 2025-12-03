<?php

namespace App\Message;

class BookingConfirmationEmail
{
    private int $bookingId;

    public function __construct(int $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }
}
