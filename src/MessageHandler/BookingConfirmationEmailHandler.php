<?php

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\BookingConfirmationEmail;
use App\Repository\BookingRepository;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class BookingConfirmationEmailHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private GoogleCalendarService $googleCalendarService,
        private EntityManagerInterface $em,
        private string $mailerFromEmail,
        private string $mailerFromName
    ) {
    }

    public function __invoke(BookingConfirmationEmail $message): void
    {
        $booking = $this->bookingRepository->find($message->getBookingId());

        if (!$booking) {
            $this->logger->error('Booking not found for confirmation email', [
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

        $emailBody = sprintf(
            "Hello %s,\n\n" .
            "Your hair salon appointment has been confirmed!\n\n" .
            "BOOKING DETAILS:\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📅 Date: %s\n" .
            "⏰ Time: %s - %s\n" .
            "✂️ Service: %s\n" .
            "💰 Price: $%s\n" .
            "👤 Stylist: %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "%s\n\n" .
            "We look forward to seeing you!\n\n" .
            "If you need to cancel or reschedule, please contact us as soon as possible.\n\n" .
            "Best regards,\n%s",
            $user->getFirstName() ?: $user->getUsername(),
            $bookingDate,
            $bookingTime,
            $endTime,
            $service->getName(),
            $service->getPrice(),
            $stylist->getName(),
            $booking->getNotes() ? "Notes: " . $booking->getNotes() : "",
            $this->mailerFromName
        );

        $email = (new Email())
            ->from($this->mailerFromEmail)
            ->to($user->getEmail())
            ->subject('✅ Appointment Confirmed - ' . $bookingDate . ' at ' . $bookingTime)
            ->text($emailBody);

        $this->mailer->send($email);

        $this->logger->info('Booking confirmation email sent', [
            'booking_id' => $booking->getId(),
            'user_email' => $user->getEmail()
        ]);

        // ==========================================
        // Google Calendar Synchronization
        // ==========================================

        $this->logger->info('Starting Google Calendar sync check...', [
            'booking_id' => $booking->getId(),
            'booking_status' => $booking->getStatus(),
            'has_calendar_event_id' => $booking->getGoogleCalendarEventId() ? 'yes' : 'no'
        ]);

        // Only sync CONFIRMED bookings (not pending/cancelled)
        if ($booking->getStatus() === Booking::STATUS_CONFIRMED) {
            $this->logger->info('Booking status is CONFIRMED - proceeding with Google Calendar sync', [
                'booking_id' => $booking->getId()
            ]);

            // Skip if already synced
            if ($booking->getGoogleCalendarEventId()) {
                $this->logger->info('Booking already has Google Calendar event ID - skipping sync', [
                    'booking_id' => $booking->getId(),
                    'event_id' => $booking->getGoogleCalendarEventId()
                ]);
            } else {
                // Attempt to sync to Google Calendar
                $this->logger->info('Checking if Google Calendar is configured...', [
                    'booking_id' => $booking->getId()
                ]);

                try {
                    if ($this->googleCalendarService->isConfigured()) {
                        $this->logger->info('Google Calendar IS CONFIGURED - attempting to create event...', [
                            'booking_id' => $booking->getId(),
                            'client_name' => $booking->getClientName(),
                            'client_email' => $booking->getClientEmail(),
                            'service' => $service->getName(),
                            'stylist' => $stylist->getName(),
                            'date' => $booking->getBookingDate()->format('Y-m-d H:i:s')
                        ]);

                        $calendarEventId = $this->googleCalendarService->createEvent($booking);

                        if ($calendarEventId) {
                            $this->logger->info('✅ SUCCESS: Google Calendar event created!', [
                                'booking_id' => $booking->getId(),
                                'event_id' => $calendarEventId,
                                'calendar_event_url' => 'https://calendar.google.com/calendar/event?eid=' . base64_encode($calendarEventId)
                            ]);

                            // Save event ID to booking
                            $booking->setGoogleCalendarEventId($calendarEventId);
                            $this->em->flush();

                            $this->logger->info('Google Calendar event ID saved to booking', [
                                'booking_id' => $booking->getId(),
                                'event_id' => $calendarEventId
                            ]);
                        } else {
                            $this->logger->warning('⚠️ WARNING: Google Calendar createEvent returned NULL', [
                                'booking_id' => $booking->getId(),
                                'possible_reasons' => [
                                    'API returned null',
                                    'Exception was caught and returned null',
                                    'Calendar service not properly initialized'
                                ]
                            ]);
                        }
                    } else {
                        $this->logger->warning('❌ Google Calendar is NOT CONFIGURED', [
                            'booking_id' => $booking->getId(),
                            'reason' => 'Service account credentials file not found or not configured',
                            'check_env_variable' => 'GOOGLE_CALENDAR_CREDENTIALS_PATH'
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('❌ EXCEPTION: Failed to sync booking to Google Calendar', [
                        'booking_id' => $booking->getId(),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString()
                    ]);
                }
            }
        } else {
            $this->logger->info('Booking status is NOT CONFIRMED - skipping Google Calendar sync', [
                'booking_id' => $booking->getId(),
                'current_status' => $booking->getStatus(),
                'note' => 'Only CONFIRMED bookings are synced to Google Calendar. Admin must confirm this booking first.'
            ]);
        }

        $this->logger->info('Google Calendar sync process completed', [
            'booking_id' => $booking->getId()
        ]);
    }
}
