<?php

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\BookingStatusChangeEmail;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class BookingStatusChangeEmailHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFromEmail,
        private string $mailerFromName
    ) {
    }

    public function __invoke(BookingStatusChangeEmail $message): void
    {
        $booking = $this->bookingRepository->find($message->getBookingId());

        if (!$booking) {
            $this->logger->error('Booking not found for status change email', [
                'booking_id' => $message->getBookingId()
            ]);
            return;
        }

        $user = $booking->getUser();
        $stylist = $booking->getStylist();
        $service = $booking->getService();

        $bookingDate = $booking->getBookingDate()->format('l, F j, Y');
        $bookingTime = $booking->getBookingDate()->format('g:i A');
        $endTime = $booking->getEndTime()->format('g:i A');

        $newStatus = $message->getNewStatus();
        $oldStatus = $message->getOldStatus();

        // Determine subject and message based on status change
        if ($newStatus === Booking::STATUS_CONFIRMED) {
            $subject = '✅ Booking Confirmed';
            $statusMessage = "Your booking has been CONFIRMED by the salon!";
            $actionMessage = "We look forward to seeing you at your appointment.";
        } elseif ($newStatus === Booking::STATUS_CANCELLED) {
            // Differentiate between refused (pending→cancelled) and cancelled (confirmed→cancelled)
            if ($oldStatus === Booking::STATUS_PENDING) {
                $subject = '🚫 Booking Refused';
                $statusMessage = "Unfortunately, we are unable to accept your booking request at this time.";
                $actionMessage = "We apologize for any inconvenience. This may be due to stylist availability or schedule conflicts.\n\n" .
                               "Please contact us directly or book a different time slot through our booking system.";
            } else {
                $subject = '❌ Booking Cancelled';
                $statusMessage = "Your booking has been CANCELLED.";
                $actionMessage = "If you did not request this cancellation, please contact us immediately.\n\n" .
                               "You can book a new appointment at any time through our booking system.";
            }
        } else {
            $subject = '📝 Booking Status Updated';
            $statusMessage = "Your booking status has been changed from " . strtoupper($oldStatus) . " to " . strtoupper($newStatus) . ".";
            $actionMessage = "If you have any questions, please contact us.";
        }

        $emailBody = sprintf(
            "Hello %s,\n\n" .
            "%s\n\n" .
            "BOOKING DETAILS:\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📅 Date: %s\n" .
            "⏰ Time: %s - %s\n" .
            "✂️ Service: %s\n" .
            "💰 Price: $%s\n" .
            "👤 Stylist: %s\n" .
            "📊 Status: %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "%s\n\n" .
            "%s\n\n" .
            "Best regards,\n%s",
            $user->getFirstName() ?: $user->getUsername(),
            $statusMessage,
            $bookingDate,
            $bookingTime,
            $endTime,
            $service->getName(),
            $service->getPrice(),
            $stylist->getName(),
            strtoupper($newStatus),
            $booking->getNotes() ? "Notes: " . $booking->getNotes() : "",
            $actionMessage,
            $this->mailerFromName
        );

        $email = (new Email())
            ->from($this->mailerFromEmail)
            ->to($user->getEmail())
            ->subject($subject . ' - ' . $bookingDate . ' at ' . $bookingTime)
            ->text($emailBody);

        $this->mailer->send($email);

        $this->logger->info('Booking status change email sent', [
            'booking_id' => $booking->getId(),
            'user_email' => $user->getEmail(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
    }
}
