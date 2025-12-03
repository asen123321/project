<?php

namespace App\Message;

class AdminBookingNotification
{
    public function __construct(
        private int $bookingId
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }
}
