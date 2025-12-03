<?php

namespace App\Message;

class BookingStatusChangeEmail
{
    public function __construct(
        private int $bookingId,
        private string $oldStatus,
        private string $newStatus
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
